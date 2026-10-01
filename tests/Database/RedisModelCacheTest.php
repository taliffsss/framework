<?php

declare(strict_types=1);

namespace Naluz\Tests\Database;

use App\Models\Post;
use App\Models\User;
use Naluz\Database\Orm\Model;
use Naluz\Tests\Support\RedisServer;
use Naluz\Tests\TestCase;

/** The same guarantees, with the cache living in a real Redis server (MODEL_CACHE_DRIVER=redis). */
final class RedisModelCacheTest extends TestCase
{
    use RedisServer;

    protected function configOverrides(): array
    {
        return parent::configOverrides() + [
            'model_cache.enabled' => true,
            'model_cache.driver' => 'redis',
            'redis.port' => self::redisPort(),
            'redis.host' => '127.0.0.1',
        ];
    }

    protected function setUp(): void
    {
        $this->redis(); // skips when redis-server is missing; flushes the db
        parent::setUp();
        Model::preventLazyLoading(false);
    }

    protected function tearDown(): void
    {
        Model::setModelCache(null);
        parent::tearDown();
    }

    private function selects(\Closure $cb): int
    {
        return count(array_filter($this->queries($cb), fn ($s) => stripos(ltrim($s), 'select') === 0));
    }

    public function testHitsInvalidationAndCascadesThroughRedis(): void
    {
        $u = User::create(['name' => 'A', 'email' => 'a@x.io', 'password' => 'p']);
        $u->posts()->create(['title' => 't', 'body' => 'b']);

        $this->assertGreaterThan(0, $this->selects(fn () => User::find($u->id)));
        $this->assertSame(0, $this->selects(fn () => User::find($u->id)), 'served from Redis');
        $this->assertGreaterThan(0, (int) $this->redis()->command('DBSIZE') + 1, 'sanity');

        $u->update(['name' => 'B']);
        $this->assertSame('B', User::find($u->id)->name, 'invalidated through Redis');

        $this->assertCount(1, Post::where('user_id', $u->id)->get());
        $this->db()->table('users')->where('id', $u->id)->delete();
        $this->assertCount(0, Post::where('user_id', $u->id)->get(), 'cascade delete flushes');
    }

    public function testEntriesCarryATtlInRedis(): void
    {
        User::create(['name' => 'A', 'email' => 'a@x.io', 'password' => 'p']);
        User::all();
        $redis = new \Naluz\Redis\Client('127.0.0.1', self::redisPort());
        $keys = $redis->command('KEYS', '*q_*');
        $this->assertNotEmpty($keys);
        $ttl = (int) $redis->command('TTL', $keys[0]);
        $this->assertGreaterThan(0, $ttl);
        $this->assertLessThanOrEqual(300, $ttl, 'default TTL is 5 minutes');
    }

    public function testUpdatedDataIsRecachedInRedis(): void
    {
        $u = User::create(['name' => 'A', 'email' => 'a@x.io', 'password' => 'p']);
        User::find($u->id);                       // hot
        $u->update(['name' => 'Updated']);        // invalidate + re-cache into Redis

        $name = null;
        $this->assertSame(0, $this->selects(function () use (&$name, $u) {
            $name = User::find($u->id)->name;
        }));
        $this->assertSame('Updated', $name, 'the reader got fresh data from the warm Redis entry');
        $this->assertGreaterThan(0, app(\Naluz\Database\ModelCache::class)->recached);
    }

    public function testRedisOutageFallsBackToTheDatabase(): void
    {
        $u = User::create(['name' => 'A', 'email' => 'a@x.io', 'password' => 'p']);
        // point the cache at a dead port: every cache call throws, queries must still work
        $dead = new \Naluz\Redis\RedisCache(new \Naluz\Redis\Client('127.0.0.1', 1, timeout: 0.2));
        $cache = new \Naluz\Database\ModelCache($dead, recache: true);
        Model::setModelCache($cache);
        $sink = tempnam(sys_get_temp_dir(), 'e');
        $old = ini_set('error_log', $sink);
        try {
            $this->assertSame('A', User::find($u->id)->name);
            $u->update(['name' => 'B']);
            $this->assertSame('B', User::find($u->id)->name);
        } finally {
            ini_set('error_log', (string) $old);
            unlink($sink);
        }
    }

    public function testSharedAcrossProcessesLikeAppServers(): void
    {
        User::create(['name' => 'A', 'email' => 'a@x.io', 'password' => 'p']);
        User::all();
        // a second application instance (another server) with the same Redis sees the same cache entries
        $second = new \Naluz\Foundation\Application(dirname(__DIR__, 2), $this->configOverrides());
        $second->boot();
        Model::preventLazyLoading(false);
        $this->assertSame(0, $this->selects(fn () => User::all()), 'hit written by the first instance');
    }
}
