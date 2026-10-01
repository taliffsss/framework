<?php

declare(strict_types=1);

namespace Naluz\Tests\Database;

use App\Models\User;
use Naluz\Cache\ArrayCache;
use Naluz\Cache\FileCache;
use Naluz\Database\Orm\Model;
use Naluz\Foundation\Application;
use Naluz\Redis\RedisCache;
use Naluz\Support\Env;
use Naluz\Tests\TestCase;

/** The MODEL_CACHING / MODEL_CACHE_DRIVER switches, read from the environment exactly as a real .env would. */
final class ModelCacheDriversTest extends TestCase
{
    private string $dir;

    protected function configOverrides(): array
    {
        // NOT overriding model_cache.* here: the flags must come from the environment
        return parent::configOverrides();
    }

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/naluz-mc-' . bin2hex(random_bytes(4));
        mkdir($this->dir . '/storage/cache', 0775, true);
        parent::setUp();
    }

    protected function tearDown(): void
    {
        Env::flush();
        Model::setModelCache(null);
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) {
            $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
        }
        rmdir($this->dir);
        parent::tearDown();
    }

    /** Boot a fresh application (real config files) with the given environment flags. */
    private function boot(array $env, array $overrides = []): Application
    {
        Env::flush();
        foreach ($env as $k => $v) {
            Env::set($k, $v);
        }
        $app = new Application(dirname(__DIR__, 2), $overrides + parent::configOverrides());
        $app->boot();
        Model::preventLazyLoading(false);
        return $app;
    }

    private function selects(Application $app, \Closure $cb): int
    {
        $log = [];
        $app->make(\Naluz\Database\DatabaseManager::class)->connection()->listen(function (string $sql) use (&$log) {
            $log[] = $sql;
        });
        $cb();
        return count(array_filter($log, fn ($s) => stripos(ltrim($s), 'select') === 0));
    }

    private function runMigrations(Application $app): void
    {
        (new \Naluz\Database\Migrations\Migrator($app->make(\Naluz\Database\DatabaseManager::class)->connection(), dirname(__DIR__, 2) . '/database/migrations'))->run();
    }

    public function testOffByDefaultMeansNoCachingAtAll(): void
    {
        $app = $this->boot([]);
        $this->runMigrations($app);
        $this->assertNull((new User())->modelCache());
        User::create(['name' => 'A', 'email' => 'a@x.io', 'password' => 'p']);
        User::all();
        $this->assertGreaterThan(0, $this->selects($app, fn () => User::all()), 'every read hits the database');
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('flags')]
    public function testFlagParsing(string $value, bool $enabled): void
    {
        $this->boot(['MODEL_CACHING' => $value, 'MODEL_CACHE_DRIVER' => 'array']);
        $this->assertSame($enabled, (new User())->modelCache() !== null, "MODEL_CACHING={$value}");
    }

    public static function flags(): array
    {
        return [['true', true], ['TRUE', true], ['1', true], ['on', true], ['false', false], ['0', false], ['', false], ['no', false], ['off', false]];
    }

    public function testLocalDriverStoresFilesAndServesFromThem(): void
    {
        $app = $this->boot(['MODEL_CACHING' => 'true', 'MODEL_CACHE_DRIVER' => 'local'], ['model_cache.ttl' => 600]);
        $this->runMigrations($app);
        User::create(['name' => 'A', 'email' => 'a@x.io', 'password' => 'p']);
        $this->assertGreaterThan(0, $this->selects($app, fn () => User::all()));
        $this->assertSame(0, $this->selects($app, fn () => User::all()));
        $files = glob(dirname(__DIR__, 2) . '/storage/cache/models/*.cache') ?: [];
        $this->assertNotEmpty($files, 'the local driver writes files under storage/cache/models');
        $this->assertSame(0, $this->selects($app, fn () => User::all()));

        // a write invalidates even though the data lives on disk
        User::create(['name' => 'B', 'email' => 'b@x.io', 'password' => 'p']);
        $this->assertCount(2, User::all());

        $stream = fopen('php://memory', 'w+');
        $kernel = new \Naluz\Console\Kernel($app, new \Naluz\Console\Output($stream));
        $this->assertSame(0, $kernel->run(['naluz', 'model-cache:prune']));
        $this->assertSame(0, $kernel->run(['naluz', 'model-cache:flush']));
        (new FileCache(dirname(__DIR__, 2) . '/storage/cache/models'))->clear();
    }

    public function testFileCachePruneRemovesOnlyExpiredEntries(): void
    {
        $cache = new FileCache($this->dir . '/storage/cache/p');
        $cache->set('live', 1, 600);
        $cache->set('dead', 1, -5);
        $cache->set('forever', 1);
        $this->assertSame(1, $cache->prune());
        $this->assertTrue($cache->has('live'));
        $this->assertTrue($cache->has('forever'));
    }

    public function testRedisDriverIsSelectedByTheFlag(): void
    {
        $app = $this->boot(['MODEL_CACHING' => 'true', 'MODEL_CACHE_DRIVER' => 'redis']);
        $store = (function () {
            return $this->store;
        })->call($app->make(\Naluz\Database\ModelCache::class));
        $this->assertInstanceOf(RedisCache::class, $store);
    }

    public function testArrayDriver(): void
    {
        $app = $this->boot(['MODEL_CACHING' => 'true', 'MODEL_CACHE_DRIVER' => 'array']);
        $store = (function () {
            return $this->store;
        })->call($app->make(\Naluz\Database\ModelCache::class));
        $this->assertInstanceOf(ArrayCache::class, $store);
    }

    public function testUnknownDriverFailsLoudly(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('MODEL_CACHE_DRIVER');
        $this->boot(['MODEL_CACHING' => 'true', 'MODEL_CACHE_DRIVER' => 'memcached']);
    }

    public function testEncryptFlag(): void
    {
        $app = $this->boot(['MODEL_CACHING' => 'true', 'MODEL_CACHE_DRIVER' => 'array', 'MODEL_CACHE_ENCRYPT' => 'true']);
        $enc = (function () {
            return $this->encrypter;
        })->call($app->make(\Naluz\Database\ModelCache::class));
        $this->assertInstanceOf(\Naluz\Security\Encrypter::class, $enc);
    }
}
