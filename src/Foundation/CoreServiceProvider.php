<?php

declare(strict_types=1);

namespace Naluz\Foundation;

use Naluz\Cache\ArrayCache;
use Naluz\Cache\FileCache;
use Naluz\Config\Repository;
use Naluz\Database\Connection;
use Naluz\Database\DatabaseManager;
use Naluz\Events\Dispatcher;
use Naluz\Log\FileLogger;
use Naluz\Routing\Router;
use Naluz\Security\Encrypter;
use Naluz\Security\Hasher;
use Naluz\Security\Jwt;
use Naluz\Session\ArraySessionHandler;
use Naluz\Session\FileSessionHandler;
use Naluz\Session\Store;
use Naluz\Redis\Client as RedisClient;
use Naluz\Redis\RedisCache;
use Naluz\Redis\RedisSessionHandler;
use Naluz\View\Factory;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;

final class CoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $app = $this->app;

        $app->singleton(Router::class, fn ($c) => new Router($c));
        $app->singleton(DatabaseManager::class, fn ($c) => new DatabaseManager((array) $c->make(Repository::class)->get('database', ['default' => 'sqlite', 'connections' => []])));
        $app->bind(Connection::class, fn ($c) => $c->make(DatabaseManager::class)->connection());

        $app->singleton(LoggerInterface::class, fn ($c) => new FileLogger(
            $c->basePath('storage/logs'),
            (string) $c->make(Repository::class)->get('app.log_level', 'debug')
        ));
        $app->singleton(RedisClient::class, fn ($c) => RedisClient::fromConfig((array) $c->make(Repository::class)->get('redis', [])));
        $app->singleton(CacheInterface::class, fn ($c) => match ($c->make(Repository::class)->get('app.cache')) {
            'array' => new ArrayCache(),
            'redis' => new RedisCache($c->make(RedisClient::class)),
            default => new FileCache($c->basePath('storage/cache/data')),
        });

        $app->singleton(Dispatcher::class, fn () => new Dispatcher());
        $app->alias(EventDispatcherInterface::class, Dispatcher::class);
        $app->alias(ListenerProviderInterface::class, Dispatcher::class);

        $app->singleton(Hasher::class, fn () => new Hasher());
        $app->singleton(Encrypter::class, function ($c) {
            $cfg = $c->make(Repository::class);
            $key = (string) $cfg->get('app.key', '');
            if ($key === '') {
                throw new \RuntimeException('No application key set. Run `php naluz key:generate`.');
            }
            return new Encrypter($key, (array) $cfg->get('app.previous_keys', []));
        });
        $app->singleton(Jwt::class, function ($c) {
            $cfg = $c->make(Repository::class);
            $secret = (string) ($cfg->get('security.jwt.secret') ?: '');
            if ($secret === '') {
                throw new \RuntimeException('Set JWT_SECRET (32+ characters) to use token authentication.');
            }
            return new Jwt($secret, $cfg->get('security.jwt.issuer'), (int) $cfg->get('security.jwt.leeway', 10));
        });

        $app->singleton(Store::class, function ($c) {
            $cfg = $c->make(Repository::class);
            $lifetime = (int) $cfg->get('session.lifetime', 7200);
            $handler = match ($cfg->get('session.driver', 'file')) {
                'array' => new ArraySessionHandler(),
                'redis' => new RedisSessionHandler($c->make(RedisClient::class), $lifetime),
                default => new FileSessionHandler($c->basePath('storage/sessions'), $lifetime),
            };
            return new Store($handler);
        });

        $app->singleton(Factory::class, fn ($c) => new Factory($c->basePath('resources/views'), $c->basePath('storage/cache/views'), $c->make(Repository::class)->get('app.env') !== 'production'));
    }
}
