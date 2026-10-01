<?php

declare(strict_types=1);

namespace Naluz\Tests\Http;

use Naluz\Console\Kernel;
use Naluz\Console\Output;
use Naluz\Foundation\Application;
use Naluz\Routing\Router;
use Naluz\Tests\TestCase;

final class RouteCacheTest extends TestCase
{
    private string $cache;

    protected function setUp(): void
    {
        $this->cache = sys_get_temp_dir() . '/naluz-routes-' . bin2hex(random_bytes(4)) . '.php';
        parent::setUp();
    }

    protected function tearDown(): void
    {
        @unlink($this->cache);
        parent::tearDown();
    }

    protected function configOverrides(): array
    {
        return parent::configOverrides() + ['app.routes_cache' => $this->cache];
    }

    private function cli(string $command, ?Application $app = null): array
    {
        $stream = fopen('php://memory', 'w+');
        $code = (new Kernel($app ?? $this->app, new Output($stream)))->run(['naluz', $command]);
        rewind($stream);
        return [$code, (string) stream_get_contents($stream)];
    }

    public function testStarterRoutesCanBeCachedAndServeRequestsFromTheCache(): void
    {
        [$code, $out] = $this->cli('route:cache');
        $this->assertSame(0, $code, $out);
        $this->assertFileExists($this->cache);

        $app = new Application(dirname(__DIR__, 2), $this->configOverrides());
        $this->app = $app;
        $this->cookies = [];
        $r = $this->json('GET', '/api/ping');
        $this->assertSame(200, $r->getStatusCode());
        $this->assertSame('ok', $this->decode($r)['status']);
        $this->assertSame(404, $this->json('GET', '/api/posts/abc')->getStatusCode());
        $this->assertSame(
            count($app->make(Router::class)->all()),
            substr_count((string) file_get_contents($this->cache), "'methods'")
        );
    }

    public function testClosureRoutesAreRefusedWithAClearMessage(): void
    {
        $this->app->make(Router::class)->get('/closure-route', fn () => 1);
        // route:cache builds a fresh router from routes/*.php, so exercise export() on the live one:
        try {
            $this->app->make(Router::class)->export();
            $this->fail();
        } catch (\LogicException $e) {
            $this->assertStringContainsString('/closure-route', $e->getMessage());
        }
    }

    public function testExportLoadRoundTrip(): void
    {
        $router = new Router($this->app);
        $router->aliasMiddleware('throttle', \Naluz\Http\Middleware\Throttle::class);
        $router->group(['prefix' => 'api', 'name' => 'api.', 'middleware' => ['throttle:5,1']], function (Router $r) {
            $r->get('/things/{id}', [\App\Http\Controllers\HomeController::class, 'index'])->where('id', '[0-9]+')->name('things.show');
            $r->post('/things', 'App\Http\Controllers\HomeController@index');
        });
        $table = $router->export();
        $this->assertCount(2, $table);

        $fresh = new Router($this->app);
        $fresh->loadCached(eval('return ' . var_export($table, true) . ';'));
        $this->assertSame('/api/things/9', $fresh->url('api.things.show', ['id' => 9]));
        [$route, $params] = $fresh->resolve(\Naluz\Http\Request::create('GET', '/api/things/9'));
        $this->assertSame(['id' => '9'], $params);
        $this->assertSame(['throttle:5,1'], $route->middleware);
        $this->expectException(\Naluz\Http\HttpException::class);
        $fresh->resolve(\Naluz\Http\Request::create('GET', '/api/things/abc'));
    }

    public function testClosureRoutesAreRejectedByExport(): void
    {
        $router = new Router($this->app);
        $router->get('/x', fn () => 1);
        $this->expectException(\LogicException::class);
        $router->export();
    }

    public function testApplicationBootsFromCacheAndSkipsRouteFiles(): void
    {
        $router = new Router($this->app);
        $router->get('/cached-only', [\App\Http\Controllers\HomeController::class, 'index'])->name('cached');
        file_put_contents($this->cache, "<?php\n\nreturn " . var_export($router->export(), true) . ";\n");

        $app = new Application(dirname(__DIR__, 2), $this->configOverrides());
        $app->boot();
        $names = array_map(fn ($r) => $r->uri, $app->make(Router::class)->all());
        $this->assertSame(['/cached-only'], $names, 'routes/*.php were not loaded');
    }

    public function testCacheIsIgnoredInDebugMode(): void
    {
        file_put_contents($this->cache, "<?php\n\nreturn [];\n");
        $app = new Application(dirname(__DIR__, 2), ['app.debug' => true] + $this->configOverrides());
        $app->boot();
        $this->assertNotEmpty($app->make(Router::class)->all());
    }

    public function testRouteClear(): void
    {
        file_put_contents($this->cache, "<?php return [];");
        [$code] = $this->cli('route:clear');
        $this->assertSame(0, $code);
        $this->assertFileDoesNotExist($this->cache);
    }
}
