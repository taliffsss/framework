<?php

declare(strict_types=1);

namespace Naluz\Tests\Database;

use App\Models\User;
use Naluz\Database\Orm\Model;
use Naluz\Queue\DatabaseQueue;
use Naluz\Queue\Payload;
use Naluz\Tests\Queue\EchoJob;
use Naluz\Tests\TestCase;
use Naluz\Validation\Validator;

require_once __DIR__ . '/../Queue/QueueContract.php';

/**
 * The whole framework on top of a LAGGING replica: the replica is a frozen copy of the schema, so any read that wrongly
 * goes there returns stale (empty) data. Features where that would be a bug must read the primary.
 */
final class ReadReplicaIntegrationTest extends TestCase
{
    private string $dir;

    protected function configOverrides(): array
    {
        $this->dir ??= sys_get_temp_dir() . '/naluz-replica-' . bin2hex(random_bytes(4));
        @mkdir($this->dir);
        return array_replace(parent::configOverrides(), [
            'database.connections.sqlite.database' => $this->dir . '/primary.sqlite',
            'database.connections.sqlite.read' => [['database' => $this->dir . '/replica.sqlite']],
            'database.connections.sqlite.sticky' => false,   // isolate routing: no read-your-writes help
            'model_cache.driver' => 'array',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp(); // runs the migrations — on the primary only
        Model::preventLazyLoading(false);
    }

    protected function tearDown(): void
    {
        Model::setModelCache(null);
        parent::tearDown();
        foreach (glob($this->dir . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->dir);
    }

    /** The replica as it was when the schema existed but before any later writes ("lag"). */
    private function snapshotReplica(): void
    {
        $this->assertFileExists($this->dir . '/primary.sqlite');
        copy($this->dir . '/primary.sqlite', $this->dir . '/replica.sqlite');
    }

    public function testMigrationsRunEntirelyOnThePrimary(): void
    {
        $this->assertFileExists($this->dir . '/primary.sqlite');
        $this->assertFileDoesNotExist($this->dir . '/replica.sqlite', 'no migration step (ran-list, hasTable, batch) touched the replica');
    }

    public function testOrdinaryModelReadsGoToTheReplicaWhichIsTheBehaviourBeingGuarded(): void
    {
        $this->snapshotReplica();
        User::create(['name' => 'A', 'email' => 'a@x.io', 'password' => 'p']);
        $this->assertCount(0, User::all(), 'sanity: without protection a read would see the stale replica');
        $this->assertCount(1, User::query()->useWritePdo()->get());
    }

    public function testModelCacheMissesAreLoadedFromThePrimaryNotTheLaggingReplica(): void
    {
        $this->snapshotReplica();
        $cacheConfig = $this->app->make(\Naluz\Config\Repository::class);
        $cache = new \Naluz\Database\ModelCache(new \Naluz\Cache\ArrayCache(), recache: true, recacheDebounce: 0);
        $manager = $this->app->make(\Naluz\Database\DatabaseManager::class);
        $cache->resolveConnectionsWith(fn ($n) => $manager->connection($n));
        $manager->listenForWrites(fn ($c, $sql, $committed, $affected) => $cache->handleWrite($c, $sql, $committed, $affected));
        Model::setModelCache($cache);

        User::create(['name' => 'A', 'email' => 'a@x.io', 'password' => 'p']);
        $this->assertCount(1, User::all(), 'the cache was filled from the primary, so it holds the new row');
        $this->assertSame(1, User::count());
        $this->assertSame('a@x.io', User::where('email', 'a@x.io')->first()?->email);

        // a subsequent change is re-cached from the primary as well
        User::create(['name' => 'B', 'email' => 'b@x.io', 'password' => 'p']);
        $this->assertCount(2, User::all());
        $this->assertSame(0, count(array_filter($this->queries(fn () => User::all()), fn ($s) => stripos($s, 'select') === 0)));
    }

    public function testWithTheOptionOffTheCacheWouldHaveBeenPoisonedByTheReplica(): void
    {
        $this->snapshotReplica();
        $cache = new \Naluz\Database\ModelCache(new \Naluz\Cache\ArrayCache(), readFromPrimary: false);
        Model::setModelCache($cache);
        User::create(['name' => 'A', 'email' => 'a@x.io', 'password' => 'p']);
        $this->assertCount(0, User::all(), 'readFromPrimary: false caches the replica\'s stale answer — which is why the default is true');
    }

    public function testUniqueAndExistsRulesCheckThePrimary(): void
    {
        $this->snapshotReplica();
        User::create(['name' => 'A', 'email' => 'taken@x.io', 'password' => 'p']);
        $db = $this->db();
        $this->assertArrayHasKey('email', Validator::make(['email' => 'taken@x.io'], ['email' => 'unique:users,email'], [], $db)->errors(), 'a lagging replica must not let a duplicate through');
        $this->assertSame([], Validator::make(['id' => 1], ['id' => 'exists:users,id'], [], $db)->errors());
    }

    public function testQueuePollingNeverReadsTheReplica(): void
    {
        $this->snapshotReplica();
        $queue = new DatabaseQueue($this->db(), $this->app->make(Payload::class));
        $queue->push(new EchoJob('hello'));
        $this->assertSame(1, $queue->size());
        $job = $queue->pop();
        $this->assertNotNull($job, 'the job exists on the primary although the replica has not seen it');
        $this->assertNull($queue->pop(), 'and a second worker cannot claim it');
    }

    public function testStickyDefaultGivesReadYourWritesAcrossTheWholeApp(): void
    {
        $this->snapshotReplica();
        $this->app->make(\Naluz\Config\Repository::class);
        $app = new \Naluz\Foundation\Application(dirname(__DIR__, 2), ['database.connections.sqlite.sticky' => true] + $this->configOverrides());
        $app->boot();
        Model::preventLazyLoading(false);
        User::create(['name' => 'A', 'email' => 'a@x.io', 'password' => 'p']);
        $this->assertCount(1, User::all(), 'after writing, the same process reads the primary');
    }
}
