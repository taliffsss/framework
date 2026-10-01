<?php

declare(strict_types=1);

namespace Naluz\Tests\Database;

use App\Models\Post;
use App\Models\User;
use Naluz\Database\ModelCache;
use Naluz\Database\Orm\Model;
use Naluz\Database\Orm\Relations\BelongsToMany;
use Naluz\Tests\TestCase;

final class CachedTag extends Model
{
    protected ?string $table = 'tags';
    protected array $fillable = ['name'];

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(CachedTaggedPost::class, 'post_tag', 'tag_id', 'post_id');
    }
}

final class CachedTaggedPost extends Model
{
    protected ?string $table = 'posts';
    protected array $fillable = ['title'];
}

final class UncachedNote extends Model
{
    protected ?string $table = 'notes';
    protected bool $cache = false;
    protected bool $timestamps = false;
    protected array $fillable = ['body'];
}

final class ModelCacheTest extends TestCase
{
    protected function configOverrides(): array
    {
        return parent::configOverrides() + ['model_cache.enabled' => true, 'model_cache.driver' => 'array'];
    }

    protected function setUp(): void
    {
        parent::setUp();
        Model::preventLazyLoading(false);
        $s = $this->db()->schema();
        $s->create('tags', function ($t) {
            $t->id();
            $t->string('name');
            $t->timestamps();
        });
        $s->create('post_tag', function ($t) {
            $t->integer('post_id');
            $t->integer('tag_id');
        });
        $s->create('notes', fn ($t) => [$t->id(), $t->string('body')]);
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

    /** Number of SELECT statements the callback sends to the database. */
    private function selects(\Closure $callback): int
    {
        return count(array_filter($this->queries($callback), fn (string $sql) => stripos(ltrim($sql), 'select') === 0));
    }

    private function user(string $email = 'a@x.io'): User
    {
        return User::create(['name' => 'Ann', 'email' => $email, 'password' => 'pw']);
    }

    // ---------------------------------------------------------------- hits

    public function testRepeatedQueriesAreServedFromTheCache(): void
    {
        $this->user();
        $first = $this->selects(fn () => User::where('email', 'a@x.io')->first());
        $second = $this->selects(fn () => $user = User::where('email', 'a@x.io')->first());
        $this->assertSame(1, $first);
        $this->assertSame(0, $second, 'second identical query never reaches the database');
        $this->assertSame(1, $this->cache()->hits);
        $this->assertSame('Ann', User::where('email', 'a@x.io')->first()->name, 'cached rows hydrate to real models');
    }

    public function testDifferentBindingsAreDifferentEntries(): void
    {
        $this->user('a@x.io');
        $this->user('b@x.io');
        $this->assertSame('a@x.io', User::where('email', 'a@x.io')->first()->email);
        $this->assertSame('b@x.io', User::where('email', 'b@x.io')->first()->email);
        $this->assertSame(0, $this->selects(fn () => User::where('email', 'a@x.io')->first()));
        $this->assertSame(0, $this->selects(fn () => User::where('email', 'b@x.io')->first()));
    }

    public function testFindAllCountExistsPluckValueAndPaginateAreCached(): void
    {
        $this->user('a@x.io');
        $this->user('b@x.io');
        $run = function () {
            User::find(1);
            User::all();
            User::count();
            User::where('id', 1)->exists();
            User::query()->pluck('email');
            User::where('id', 2)->value('email');
            User::query()->orderBy('id')->paginate(1);
        };
        $this->assertGreaterThan(0, $this->selects($run));
        $this->assertSame(0, $this->selects($run), 'the whole second pass is served from cache');
        $this->assertSame(['a@x.io', 'b@x.io'], User::query()->pluck('email')->all());
        $this->assertSame(2, User::count());
    }

    public function testNullAndEmptyResultsAreCachedToo(): void
    {
        $this->assertNull(User::find(999));
        $this->assertSame(0, $this->selects(fn () => User::find(999)), 'a miss is cached as a miss');
        $this->assertCount(0, User::all());
        $this->assertSame(0, $this->selects(fn () => User::all()));
    }

    public function testEagerLoadedRelationsAreCached(): void
    {
        $u = $this->user();
        $u->posts()->create(['title' => 'p1', 'body' => 'b']);
        $this->assertGreaterThan(0, $this->selects(fn () => User::with('posts')->get()));
        $this->assertSame(0, $this->selects(fn () => User::with('posts')->get()));
    }

    // ---------------------------------------------------------------- invalidation

    public function testSaveUpdateDeleteAndCreateInvalidate(): void
    {
        $u = $this->user();
        $this->assertSame('Ann', User::find($u->id)->name);

        $u->update(['name' => 'Anna']);
        $this->assertSame('Anna', User::find($u->id)->name, 'update is visible immediately');

        $this->user('b@x.io');
        $this->assertSame(2, User::count(), 'create invalidates aggregates');

        User::find($u->id)->delete();
        $this->assertNull(User::find($u->id), 'delete is visible immediately');
        $this->assertSame(1, User::count());
    }

    public function testQueryBuilderAndBulkWritesInvalidateToo(): void
    {
        $u = $this->user();
        $this->assertSame('Ann', User::find($u->id)->name);

        $this->db()->table('users')->where('id', $u->id)->update(['name' => 'ViaBuilder']);
        $this->assertSame('ViaBuilder', User::find($u->id)->name, 'raw query-builder write invalidates the model cache');

        User::where('id', $u->id)->update(['name' => 'ViaOrm']);
        $this->assertSame('ViaOrm', User::find($u->id)->name);

        $this->db()->table('users')->where('id', $u->id)->increment('id', 0);
        $this->db()->statement('UPDATE users SET name = ? WHERE id = ?', ['ViaRawSql', $u->id]);
        $this->assertSame('ViaRawSql', User::find($u->id)->name, 'raw SQL statements invalidate as well');

        User::where('id', $u->id)->delete();
        $this->assertNull(User::find($u->id));
    }

    public function testWritesToOtherTablesLeaveUnrelatedEntriesAlone(): void
    {
        $this->user();
        User::all();
        UncachedNote::create(['body' => 'x']);
        $this->db()->table('tags')->insert(['name' => 't']);
        $this->assertSame(0, $this->selects(fn () => User::all()), 'INSERT into another table does not flush users');
    }

    public function testJoinedTablesInvalidateDependentQueries(): void
    {
        $u = $this->user();
        $p = $u->posts()->create(['title' => 'p', 'body' => 'b']);
        $tag = CachedTag::create(['name' => 'php']);
        $this->db()->table('post_tag')->insert(['post_id' => $p->id, 'tag_id' => $tag->id]);

        $tags = fn () => CachedTag::find($tag->id)->posts()->get()->pluck('title')->all();
        $this->assertSame(['p'], $tags());
        $this->assertSame(['p'], $tags());

        $this->db()->table('post_tag')->where('tag_id', $tag->id)->delete(); // only the pivot changes
        $this->assertSame([], $tags(), 'the join through the pivot table is invalidated by pivot writes');
    }

    public function testBelongsToManySyncInvalidates(): void
    {
        $u = $this->user();
        $p = $u->posts()->create(['title' => 'p', 'body' => 'b']);
        $a = CachedTag::create(['name' => 'a']);
        $b = CachedTag::create(['name' => 'b']);
        $rel = fn () => (new CachedTaggedPost())->newFromBuilder(['id' => $p->id])
            ->belongsToMany(CachedTag::class, 'post_tag', 'post_id', 'tag_id');

        $rel()->attach([$a->id]);
        $this->assertSame(['a'], $rel()->get()->pluck('name')->all());
        $rel()->sync([$b->id]);
        $this->assertSame(['b'], $rel()->get()->pluck('name')->all());
    }

    public function testDeleteFlushesEverythingSoForeignKeyCascadesAreNotStale(): void
    {
        $u = $this->user();
        $u->posts()->create(['title' => 'p', 'body' => 'b']);
        $this->assertCount(1, Post::where('user_id', $u->id)->get());
        $this->assertSame(1, Post::where('user_id', $u->id)->count());

        $this->db()->table('users')->where('id', $u->id)->delete(); // ON DELETE CASCADE removes the posts
        $this->assertCount(0, Post::where('user_id', $u->id)->get(), 'cascade-deleted rows are not served from cache');
        $this->assertSame(0, Post::where('user_id', $u->id)->count());
    }

    public function testFlushOnDeleteTableModeOnlyFlushesTheNamedTable(): void
    {
        $cache = new ModelCache(new \Naluz\Cache\ArrayCache(), flushOnDelete: 'table');
        $this->app->make(\Naluz\Database\DatabaseManager::class)->listenForWrites(fn ($c, $sql) => $cache->handleWrite($c, $sql));
        Model::setModelCache($cache);
        $this->user();
        User::all();
        Post::count();
        $this->db()->table('tags')->insert(['name' => 'x']);
        $this->db()->table('tags')->delete();
        $this->assertSame(0, $this->selects(fn () => User::all()), 'delete on tags did not flush users in "table" mode');
    }

    public function testDdlFlushesEverything(): void
    {
        $this->user();
        User::all();
        $this->db()->schema()->create('extra', fn ($t) => $t->id());
        $this->assertGreaterThan(0, $this->selects(fn () => User::all()));
    }

    public function testModelFlushCacheAndCommand(): void
    {
        $this->user();
        User::all();
        User::flushCache();
        $this->assertGreaterThan(0, $this->selects(fn () => User::all()));
        User::all();

        $stream = fopen('php://memory', 'w+');
        $kernel = new \Naluz\Console\Kernel($this->app, new \Naluz\Console\Output($stream));
        $this->assertSame(0, $kernel->run(['naluz', 'model-cache:flush']));
        $this->assertGreaterThan(0, $this->selects(fn () => User::all()));
    }

    // ---------------------------------------------------------------- transactions

    public function testReadsInsideTransactionsBypassTheCacheAndNeverStoreUncommittedData(): void
    {
        $u = $this->user();
        $this->assertSame('Ann', User::find($u->id)->name);

        try {
            $this->db()->transaction(function () use ($u) {
                User::where('id', $u->id)->update(['name' => 'InTx']);
                $this->assertSame('InTx', User::find($u->id)->name, 'sees its own uncommitted write');
                throw new \RuntimeException('rollback');
            });
        } catch (\RuntimeException) {
        }
        $this->assertSame('Ann', User::find($u->id)->name, 'rolled-back data was never cached');
    }

    public function testCommitInvalidatesAgainSoNothingStaleSurvivesIt(): void
    {
        $u = $this->user();
        $query = User::query()->where('users.id', '=', $u->id)->limit(1);
        $fingerprint = 'rows|' . $query->toSql() . '|' . json_encode([$query->getBindings(), []], JSON_PARTIAL_OUTPUT_ON_ERROR);
        $old = [User::query()->withoutCache()->find($u->id)->getRawAttributes()];

        $this->db()->transaction(function () use ($u, $fingerprint, $old) {
            User::where('id', $u->id)->update(['name' => 'Committed']);
            // Another request, outside our transaction, reads the OLD committed row and caches it under the
            // (already bumped) token — exactly the race that a bump-only-at-write strategy would lose.
            $this->cache()->remember('sqlite', ['users'], $fingerprint, null, fn () => $old);
        });
        $this->assertSame('Committed', User::find($u->id)->name, 'the post-COMMIT bump hides the entry cached during the transaction');
    }

    // ---------------------------------------------------------------- opt-outs & safety

    public function testWithoutCacheAndFreshAndRefreshReadLive(): void
    {
        $u = $this->user();
        User::find($u->id);
        $this->assertGreaterThan(0, $this->selects(fn () => User::query()->withoutCache()->find($u->id)));
        $this->assertGreaterThan(0, $this->selects(fn () => $u->fresh()));
        $this->db()->table('users')->where('id', $u->id)->update(['name' => 'Changed']);
        $this->assertSame('Changed', $u->refresh()->name);
    }

    public function testModelsCanOptOut(): void
    {
        UncachedNote::create(['body' => 'x']);
        UncachedNote::all();
        $this->assertGreaterThan(0, $this->selects(fn () => UncachedNote::all()), 'protected bool $cache = false');
    }

    public function testExcludedTablesAreNeverCached(): void
    {
        $this->app->make(\Naluz\Config\Repository::class)->set('model_cache.exclude_tables', ['users']);
        $cache = new ModelCache(new \Naluz\Cache\ArrayCache(), excludeTables: ['users']);
        Model::setModelCache($cache);
        $this->user();
        User::all();
        $this->assertGreaterThan(0, $this->selects(fn () => User::all()));
    }

    public function testQueriesWithRawSqlAreNeverCached(): void
    {
        $this->user();
        $raw = fn () => User::query()->whereRaw('LOWER(name) = ?', ['ann'])->get();
        $raw();
        $this->assertGreaterThan(0, $this->selects($raw), 'raw SQL may depend on tables we cannot see');
        $sel = fn () => User::query()->selectRaw('count(*) as n')->get();
        $sel();
        $this->assertGreaterThan(0, $this->selects($sel));
    }

    public function testCacheForOverridesTheTtl(): void
    {
        $store = new class extends \Naluz\Cache\ArrayCache {
            public array $ttls = [];

            public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool
            {
                if (str_contains($key, 'q_')) {
                    $this->ttls[] = $ttl;
                }
                return parent::set($key, $value, $ttl);
            }
        };
        Model::setModelCache(new ModelCache($store, ttl: 100));
        $this->user();
        User::query()->cacheFor(7)->get();
        User::where('id', 1)->get();
        $this->assertSame([7, 100], $store->ttls);
    }

    public function testSoftDeleteScopesAreSeparateEntries(): void
    {
        $u = $this->user();
        $p = $u->posts()->create(['title' => 'p', 'body' => 'b']);
        $this->assertCount(1, Post::all());
        $p->delete();
        $this->assertCount(0, Post::all(), 'soft delete (an UPDATE) invalidates');
        $this->assertCount(1, Post::withTrashed()->get());
        $p->restore();
        $this->assertCount(1, Post::all());
    }

    public function testCountersReportHitsAndMisses(): void
    {
        $this->user();
        User::all();
        User::all();
        User::all();
        $this->assertSame(2, $this->cache()->hits);
        $this->assertGreaterThanOrEqual(1, $this->cache()->misses);
    }

    public function testEncryptedStoreHoldsNoPlaintext(): void
    {
        $store = new \Naluz\Cache\ArrayCache();
        Model::setModelCache(new ModelCache($store, encrypter: $this->app->make(\Naluz\Security\Encrypter::class)));
        $this->user();
        User::where('email', 'a@x.io')->first();
        $dump = serialize((function () {
            return $this->store;
        })->call($store));
        $this->assertStringNotContainsString('a@x.io', $dump);
        $this->assertSame('Ann', User::where('email', 'a@x.io')->first()->name, 'still hits and decrypts');
    }

    public function testTamperedEncryptedEntriesAreTreatedAsMisses(): void
    {
        $store = new \Naluz\Cache\ArrayCache();
        Model::setModelCache(new ModelCache($store, encrypter: $this->app->make(\Naluz\Security\Encrypter::class)));
        $this->user();
        User::all();
        $keys = array_filter(array_keys((function () {
            return $this->store;
        })->call($store)), fn ($k) => str_contains($k, 'q_'));
        foreach ($keys as $k) {
            $store->set($k, 'garbage-not-a-payload');
        }
        $this->assertCount(1, User::all(), 'falls back to the database instead of failing or trusting the entry');
    }
}
