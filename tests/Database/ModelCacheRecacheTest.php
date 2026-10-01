<?php

declare(strict_types=1);

namespace Naluz\Tests\Database;

use App\Models\Post;
use App\Models\User;
use Naluz\Cache\ArrayCache;
use Naluz\Database\DatabaseManager;
use Naluz\Database\ModelCache;
use Naluz\Database\Orm\Model;
use Naluz\Tests\TestCase;
use Psr\SimpleCache\CacheInterface;

final class RecordingCache extends ArrayCache
{
    /** @var list<int|null> TTLs of data entries (not version tokens / registry) */
    public array $dataTtls = [];

    public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool
    {
        if (str_contains($key, '_q_')) {
            $this->dataTtls[] = is_int($ttl) ? $ttl : null;
        }
        return parent::set($key, $value, $ttl);
    }

    public function dump(): string
    {
        return serialize($this->store);
    }
}

final class ModelCacheRecacheTest extends TestCase
{
    protected function configOverrides(): array
    {
        return parent::configOverrides() + ['model_cache.enabled' => true, 'model_cache.driver' => 'array'];
    }

    protected function setUp(): void
    {
        parent::setUp();
        Model::preventLazyLoading(false);
    }

    protected function tearDown(): void
    {
        Model::setModelCache(null);
        parent::tearDown();
    }

    private function cache(): ModelCache
    {
        return $this->app->make(ModelCache::class);
    }

    private function selects(\Closure $cb): int
    {
        return count(array_filter($this->queries($cb), fn (string $s) => stripos(ltrim($s), 'select') === 0));
    }

    /** Install a fresh ModelCache with custom settings on the live connection. */
    private function install(CacheInterface $store, array $options = []): ModelCache
    {
        $cache = new ModelCache($store, ...($options + ['recache' => true, 'recacheDebounce' => 0]));
        $manager = $this->app->make(DatabaseManager::class);
        $cache->resolveConnectionsWith(fn (string $n) => $manager->connection($n));
        $manager->listenForWrites(fn ($c, $sql, $committed, $affected) => $cache->handleWrite($c, $sql, $committed, $affected));
        Model::setModelCache($cache);
        return $cache;
    }

    private function user(string $email = 'a@x.io', string $name = 'Ann'): User
    {
        return User::create(['name' => $name, 'email' => $email, 'password' => 'pw']);
    }

    // ---------------------------------------------------------------- TTL

    public function testDefaultTtlIsFiveMinutes(): void
    {
        $this->assertSame(300, ModelCache::DEFAULT_TTL);
        $this->assertSame(300, (new ModelCache(new ArrayCache()))->defaultTtl());

        $store = new RecordingCache();
        $this->install($store);
        $this->user();
        User::all();
        $this->assertContains(300, $store->dataTtls, 'cached entries expire after 300 seconds');
    }

    public function testConfigAndEnvDefaultsAreFiveMinutes(): void
    {
        \Naluz\Support\Env::flush();
        $cfg = require dirname(__DIR__, 2) . '/config/model_cache.php';
        $this->assertSame(300, $cfg['ttl']);
        $this->assertTrue($cfg['recache']);
        $this->assertStringContainsString('MODEL_CACHE_TTL=300', (string) file_get_contents(dirname(__DIR__, 2) . '/.env.example'));
    }

    public function testTtlIsConfigurable(): void
    {
        $store = new RecordingCache();
        $this->install($store, ['ttl' => 42]);
        $this->user();
        User::all();
        $this->assertContains(42, $store->dataTtls);
    }

    // ---------------------------------------------------------------- re-caching on new / updated data

    public function testNewDataIsRecachedSoTheNextReaderHitsAWarmCache(): void
    {
        $this->user('a@x.io');
        $this->assertCount(1, User::all());                 // becomes a "hot" query
        $this->user('b@x.io');                              // new data → invalidate + re-cache

        $this->assertGreaterThan(0, $this->cache()->recached);
        $rows = [];
        $selects = $this->selects(function () use (&$rows) {
            $rows = User::all();
        });
        $this->assertSame(0, $selects, 'the reader did not touch the database');
        $this->assertCount(2, $rows, 'and it received the new row');
    }

    public function testUpdatedDataIsRecached(): void
    {
        $u = $this->user();
        $this->assertSame('Ann', User::find($u->id)->name);
        $u->update(['name' => 'Anna']);

        $name = null;
        $this->assertSame(0, $this->selects(function () use (&$name, $u) {
            $name = User::find($u->id)->name;
        }));
        $this->assertSame('Anna', $name);
    }

    public function testDeletedDataIsRecached(): void
    {
        $a = $this->user('a@x.io');
        $this->user('b@x.io');
        $this->assertCount(2, User::all());
        $a->delete();
        $count = null;
        $this->assertSame(0, $this->selects(function () use (&$count) {
            $count = User::all()->count();
        }));
        $this->assertSame(1, $count);
    }

    public function testEveryKindOfReadIsRecachedWithTheSameResultTheDatabaseGives(): void
    {
        $u = $this->user('a@x.io', 'Ann');
        $u->posts()->create(['title' => 'p1', 'body' => 'b', 'published' => true]);
        $reads = [
            'all' => fn () => User::query()->orderBy('id')->get()->pluck('email')->all(),
            'find' => fn () => User::find(1)?->email,
            'count' => fn () => User::count(),
            'sum' => fn () => Post::sum('user_id'),
            'min' => fn () => User::min('id'),
            'max' => fn () => User::max('id'),
            'avg' => fn () => User::avg('id'),
            'exists' => fn () => User::where('email', 'a@x.io')->exists(),
            'doesntExist' => fn () => User::where('email', 'zzz')->doesntExist(),
            'value' => fn () => User::where('id', 1)->value('email'),
            'pluck' => fn () => User::query()->orderBy('id')->pluck('email')->all(),
            'pluck keyed' => fn () => User::query()->orderBy('id')->pluck('name', 'email')->all(),
            'paginate' => fn () => User::query()->orderBy('id')->paginate(10)->jsonSerialize(),
            'eager' => fn () => User::with('posts')->first()->toArray(),
        ];
        foreach ($reads as $read) {
            $read(); // make every query hot
        }
        $this->user('b@x.io', 'Bob'); // change the data → everything is invalidated and re-cached

        foreach ($reads as $name => $read) {
            $cached = null;
            $selects = $this->selects(function () use (&$cached, $read) {
                $cached = $read();
            });
            $live = Model::runWithoutCache($read);
            $this->assertSame(0, $selects, "{$name}: served from the re-cached entry");
            $this->assertEquals($live, $cached, "{$name}: re-cached value equals a live database read");
        }
    }

    public function testOnlyQueriesOnTheChangedTablesAreRerun(): void
    {
        $u = $this->user();
        $u->posts()->create(['title' => 'p', 'body' => 'b']);
        User::all();
        Post::all();
        $before = $this->cache()->recached;

        $this->user('b@x.io'); // INSERT into users: posts queries stay valid and are not re-run
        $this->assertSame(1, $this->cache()->recached - $before, 'only the users query was refreshed');
        $this->assertSame(0, $this->selects(fn () => Post::all()), 'posts entry is still valid');
    }

    // ---------------------------------------------------------------- when NOT to invalidate / recache

    public function testWritesThatChangeNothingInvalidateAndRecacheNothing(): void
    {
        $this->user();
        User::all();
        $before = $this->cache()->recached;

        User::where('id', 999)->update(['name' => 'ghost']);     // matches no rows
        User::where('id', 999)->delete();                        // matches no rows (a delete would flush everything)
        $this->assertSame($before, $this->cache()->recached);
        $this->assertSame(0, $this->selects(fn () => User::all()), 'cache untouched by no-op writes');
    }

    public function testRecacheWaitsForCommit(): void
    {
        $this->user('a@x.io');
        User::all();
        $before = $this->cache()->recached;
        $this->db()->transaction(function () {
            $this->user('b@x.io');
            $this->assertCount(2, User::all(), 'inside the transaction: live read, own writes visible');
        });
        $this->assertGreaterThan($before, $this->cache()->recached, 're-cached after COMMIT');
        $this->assertSame(0, $this->selects(fn () => User::all()));
        $this->assertCount(2, User::all());
    }

    public function testRolledBackTransactionsRecacheNothingFromUncommittedData(): void
    {
        $this->user('a@x.io');
        User::all();
        try {
            $this->db()->transaction(function () {
                $this->user('b@x.io');
                throw new \RuntimeException('no');
            });
        } catch (\RuntimeException) {
        }
        $this->assertCount(1, User::all(), 'the rolled-back user never reaches the cache');
    }

    public function testDdlDoesNotRecache(): void
    {
        $this->user();
        User::all();
        $before = $this->cache()->recached;
        $this->db()->schema()->create('extra_table', fn ($t) => $t->id());
        $this->assertSame($before, $this->cache()->recached);
        $this->assertGreaterThan(0, $this->selects(fn () => User::all()), 'but the cache was flushed');
    }

    public function testBurstsOfWritesRecacheOnce(): void
    {
        $this->install(new ArrayCache(), ['recacheDebounce' => 60]);
        $this->user('a@x.io');
        User::all();
        $m = (new \ReflectionClass(Model::class))->getProperty('modelCache')->getValue();
        $this->user('b@x.io');   // 1st write after the hot query exists: re-caches
        $afterFirst = $m->recached;
        $this->user('c@x.io');   // inside the debounce window: invalidates, but no second re-cache
        $this->assertSame($afterFirst, $m->recached);
        $this->assertCount(3, User::all(), 'the data is still correct (lazy re-cache on read)');
    }

    public function testRecacheLimitBoundsTheCostOfAWrite(): void
    {
        $m = $this->install(new ArrayCache(), ['recacheLimit' => 2]);
        $this->user();
        User::count();
        User::max('id');
        User::min('id');            // 3 distinct queries; only the 2 most recent are remembered
        $before = $m->recached;
        $this->user('b@x.io');
        $this->assertSame(2, $m->recached - $before);
    }

    public function testRecacheCanBeSwitchedOff(): void
    {
        $m = $this->install(new ArrayCache(), ['recache' => false]);
        $this->user();
        User::all();
        $this->user('b@x.io');
        $this->assertSame(0, $m->recached);
        $this->assertGreaterThan(0, $this->selects(fn () => User::all()), 'plain invalidation: the next read goes to the database');
        $this->assertSame(0, $this->selects(fn () => User::all()), '…and then it is cached again');
    }

    public function testAFailingRecacheNeverBreaksTheWrite(): void
    {
        $m = $this->install(new ArrayCache());
        $m->remember('sqlite', ['users'], 'bad|fp', null, fn () => [], ['kind' => 'rows', 'sql' => 'SELECT * FROM table_that_does_not_exist', 'bindings' => [], 'args' => []]);
        $u = $this->user();            // must not throw although the remembered query is broken
        $this->assertTrue($u->exists);
        $this->user('b@x.io');         // the broken entry was forgotten, later writes are fine too
        $this->assertSame(2, User::count());
    }

    // ---------------------------------------------------------------- resilience

    public function testCacheStoreFailureFallsBackToTheDatabase(): void
    {
        $down = new class extends ArrayCache {
            public function get(string $key, mixed $default = null): mixed
            {
                throw new \RuntimeException('redis is down');
            }

            public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool
            {
                throw new \RuntimeException('redis is down');
            }

            public function getMultiple(iterable $keys, mixed $default = null): iterable
            {
                throw new \RuntimeException('redis is down');
            }
        };
        $sink = tempnam(sys_get_temp_dir(), 'e');
        $old = ini_set('error_log', $sink);
        try {
            $this->install($down);
            $u = $this->user();                              // writes still work
            $this->assertSame('Ann', User::find($u->id)->name); // reads still work, straight from the database
            $this->assertCount(1, User::all());
        } finally {
            ini_set('error_log', (string) $old);
        }
        $this->assertStringContainsString('Model cache unavailable', (string) file_get_contents($sink));
        unlink($sink);
    }

    public function testWithoutFallbackStoreFailuresSurface(): void
    {
        $down = new class extends ArrayCache {
            public function getMultiple(iterable $keys, mixed $default = null): iterable
            {
                throw new \RuntimeException('redis is down');
            }
        };
        $this->install($down, ['fallback' => false]);
        $this->expectException(\RuntimeException::class);
        User::all();
    }

    // ---------------------------------------------------------------- controls

    public function testRunWithoutCacheBypassesReadsOnly(): void
    {
        $this->user();
        User::all();
        $this->assertSame(0, $this->selects(fn () => User::all()));
        $inside = $this->selects(fn () => Model::runWithoutCache(function () {
            User::all();
            User::all();
        }));
        $this->assertSame(2, $inside, 'both reads hit the database inside the block');
        $this->assertSame(0, $this->selects(fn () => User::all()), 'normal caching resumes afterwards');
    }

    public function testRunWithoutCacheStillInvalidatesOnWrites(): void
    {
        $u = $this->user();
        User::find($u->id);
        Model::runWithoutCache(fn () => $u->update(['name' => 'Changed']));
        $this->assertSame('Changed', User::find($u->id)->name);
    }

    public function testFlushCommandCanTargetOneModel(): void
    {
        $this->user();
        $u = User::first();
        Post::factory()->create(['user_id' => $u->id]);
        User::all();
        Post::all();

        $run = function (array $args) {
            $stream = fopen('php://memory', 'w+');
            return (new \Naluz\Console\Kernel($this->app, new \Naluz\Console\Output($stream)))->run(['naluz', 'model-cache:flush', ...$args]);
        };
        $this->assertSame(0, $run(['--model=' . Post::class]));
        $this->assertSame(0, $this->selects(fn () => User::all()), 'users untouched');
        $this->assertGreaterThan(0, $this->selects(fn () => Post::all()), 'posts flushed');
        $this->assertSame(1, $run(['--model=stdClass']));
    }

    public function testRememberedQueriesAreEncryptedWhenEncryptionIsOn(): void
    {
        $store = new RecordingCache();
        $this->install($store, ['encrypter' => $this->app->make(\Naluz\Security\Encrypter::class)]);
        $this->user('secret.person@x.io');
        User::where('email', 'secret.person@x.io')->first();
        $this->assertStringNotContainsString('secret.person@x.io', $store->dump(), 'neither rows nor remembered bindings are stored in clear text');
        $this->user('other@x.io');
        $this->assertSame('secret.person@x.io', User::where('email', 'secret.person@x.io')->first()->email);
    }

    // ---------------------------------------------------------------- the plan mirrors the real query

    public function testReadPlansMatchTheSqlTheBuilderActuallyRuns(): void
    {
        $this->user();
        $db = $this->db();
        $cases = [
            ['count', [], fn ($q) => $q->count()],
            ['count', ['email'], fn ($q) => $q->count('email')],
            ['sum', ['id'], fn ($q) => $q->sum('id')],
            ['min', ['id'], fn ($q) => $q->min('id')],
            ['max', ['id'], fn ($q) => $q->max('id')],
            ['avg', ['id'], fn ($q) => $q->avg('id')],
            ['exists', [], fn ($q) => $q->exists()],
            ['value', ['email'], fn ($q) => $q->value('email')],
            ['pluck', ['email'], fn ($q) => $q->pluck('email')],
            ['pluck', ['name', 'email'], fn ($q) => $q->pluck('name', 'email')],
            ['rows', [], fn ($q) => $q->get()],
        ];
        foreach ($cases as [$kind, $args, $run]) {
            $make = fn () => $db->table('users')->where('id', '>', 0)->orderBy('id')->limit(5);
            $executed = $this->queries(fn () => $run($make()));
            [$sql, $bindings] = $make()->readPlan($kind, $args);
            $this->assertSame($executed[0], $sql, "{$kind}(" . implode(',', $args) . ')');
            $this->assertSame([0], $bindings);
        }
    }
}
