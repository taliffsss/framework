<?php

declare(strict_types=1);

namespace Naluz\Database;

use Naluz\Cache\ArrayCache;
use Naluz\Cache\FileCache;
use Naluz\Config\Repository;
use Naluz\Database\Orm\Model;
use Naluz\Foundation\ServiceProvider;
use Naluz\Redis\Client as RedisClient;
use Naluz\Redis\RedisCache;
use Naluz\Security\Encrypter;
use Psr\SimpleCache\CacheInterface;

/** Wires the model cache when MODEL_CACHING=true; costs nothing (no listeners, no lookups) when it is off. */
final class ModelCacheServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ModelCache::class, function ($c) {
            $cfg = $c->make(Repository::class);
            $store = $this->store($cfg);
            $prefix = (string) $cfg->get('model_cache.prefix', 'naluz_mc_');
            return new ModelCache(
                $store,
                (int) $cfg->get('model_cache.ttl', 3600),
                $prefix,
                (array) $cfg->get('model_cache.exclude_tables', []),
                (string) $cfg->get('model_cache.flush_on_delete', 'all'),
                $cfg->get('model_cache.encrypt') ? $c->make(Encrypter::class) : null
            );
        });
    }

    public function boot(): void
    {
        $cfg = $this->app->make(Repository::class);
        if (!$cfg->get('model_cache.enabled')) {
            Model::setModelCache(null);
            return;
        }
        $cache = $this->app->make(ModelCache::class);
        $this->app->make(DatabaseManager::class)->listenForWrites(
            fn (string $connection, string $sql) => $cache->handleWrite($connection, $sql)
        );
        Model::setModelCache($cache);
    }

    private function store(Repository $cfg): CacheInterface
    {
        $driver = (string) $cfg->get('model_cache.driver', 'local');
        return match ($driver) {
            'redis' => new RedisCache($this->app->make(RedisClient::class), (string) $cfg->get('model_cache.prefix', 'naluz_mc_') . 'data:'),
            'local' => new FileCache($this->app->basePath('storage/cache/models')),
            'array' => new ArrayCache(),
            default => throw new \InvalidArgumentException("MODEL_CACHE_DRIVER must be local, redis or array; got [{$driver}]."),
        };
    }
}
