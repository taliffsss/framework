<?php

declare(strict_types=1);

namespace Naluz\Tests;

use Naluz\Database\Connection;
use Naluz\Database\DatabaseManager;
use Naluz\Database\Migrations\Migrator;
use Naluz\Database\Orm\Model;
use Naluz\Foundation\Application;
use Naluz\Http\Request;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

abstract class TestCase extends BaseTestCase
{
    protected Application $app;
    /** @var array<string,string> cookie jar shared by requests in one test */
    protected array $cookies = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->cookies = [];
        Model::flushEventListeners();
        $this->app = new Application(dirname(__DIR__), $this->configOverrides());
        $this->app->boot();
        // cheap argon2 parameters keep the suite fast; production uses PHP's secure defaults
        $this->app->instance(\Naluz\Security\Hasher::class, new \Naluz\Security\Hasher(['memory_cost' => 1024, 'time_cost' => 1, 'threads' => 1]));
        if ($this->migrate()) {
            (new Migrator($this->db(), dirname(__DIR__) . '/database/migrations'))->run();
        }
    }

    /** @return array<string,mixed> */
    protected function configOverrides(): array
    {
        return [
            'app.debug' => false,
            'app.key' => 'base64:' . base64_encode(str_repeat('k', 32)),
            'app.cache' => 'array',
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'session.driver' => 'array',
            'logging.default' => 'null', // tests never write to storage/logs; assert via LogManager::extend()
            'security.jwt.secret' => str_repeat('s', 40),
            'security.jwt.issuer' => 'tests',
        ];
    }

    protected function migrate(): bool
    {
        return true;
    }

    protected function db(): Connection
    {
        return $this->app->make(DatabaseManager::class)->connection();
    }

    protected function send(ServerRequestInterface $request): ResponseInterface
    {
        $jar = [];
        foreach ($this->cookies as $k => $v) {
            $jar[$k] = $v;
        }
        $response = $this->app->handle($request->withCookieParams($jar + $request->getCookieParams()));
        foreach ($response->getHeader('Set-Cookie') as $cookie) {
            [$pair] = explode(';', $cookie, 2);
            [$name, $value] = explode('=', $pair, 2);
            $this->cookies[$name] = $value;
        }
        return $response;
    }

    /** Register extra routes for a test (inside the `web` or `api` middleware group). */
    protected function routes(\Closure $define, array $group = ['middleware' => ['web']]): void
    {
        $this->app->make(\Naluz\Routing\Router::class)->group($group, $define);
    }

    protected function get(string $uri, array $headers = []): ResponseInterface
    {
        return $this->send(Request::create('GET', $uri, [], $headers));
    }

    /** Send a JSON body. */
    protected function json(string $method, string $uri, array $data = [], array $headers = []): ResponseInterface
    {
        $factory = new Psr17Factory();
        $request = (new ServerRequest($method, $uri, $headers + ['Content-Type' => 'application/json', 'Accept' => 'application/json']))
            ->withBody($factory->createStream(json_encode($data)));
        return $this->send($request);
    }

    protected function form(string $method, string $uri, array $data = [], array $headers = []): ResponseInterface
    {
        return $this->send(Request::create($method, $uri, $data, $headers));
    }

    protected function decode(ResponseInterface $response): array
    {
        return json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }

    /** Collects every SQL statement executed while the callback runs. @return list<string> */
    protected function queries(\Closure $callback): array
    {
        $log = [];
        $this->db()->listen(function (string $sql) use (&$log): void {
            $log[] = $sql;
        });
        $callback();
        return $log;
    }
}
