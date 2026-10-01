<?php

declare(strict_types=1);

namespace Naluz\Tests\Http;

use App\Models\Post;
use App\Models\User;
use Naluz\Http\HttpException;
use Naluz\Routing\Router;
use Naluz\Tests\TestCase;

final class ApiTest extends TestCase
{
    private function seed(): User
    {
        $u = User::create(['name' => 'Ann', 'email' => 'a@x.io', 'password' => 'pw']);
        foreach (range(1, 20) as $i) {
            $u->posts()->create(['title' => "Post {$i}", 'body' => 'b', 'published' => true]);
        }
        $u->posts()->create(['title' => 'Draft', 'body' => 'b', 'published' => false]);
        return $u;
    }

    public function testPing(): void
    {
        $r = $this->json('GET', '/api/ping');
        $this->assertSame(200, $r->getStatusCode());
        $this->assertStringStartsWith('application/json', $r->getHeaderLine('Content-Type'));
        $this->assertSame(['status' => 'ok', 'framework' => 'NaluzPHP'], $this->decode($r));
    }

    public function testApiIsStateless(): void
    {
        $r = $this->json('GET', '/api/ping');
        $this->assertFalse($r->hasHeader('Set-Cookie'));
    }

    public function testListIsPaginatedAndHidesDrafts(): void
    {
        $this->seed();
        $body = $this->decode($this->json('GET', '/api/posts?per_page=5&page=2'));
        $this->assertCount(5, $body['data']);
        $this->assertSame(20, $body['meta']['total']);
        $this->assertSame(4, $body['meta']['last_page']);
        $this->assertSame(2, $body['meta']['current_page']);
        $this->assertSame('a@x.io', $body['data'][0]['author']['email']);
        $this->assertArrayNotHasKey('password', $body['data'][0]['author']);
    }

    public function testCreateShowUpdateDelete(): void
    {
        $u = $this->seed();
        $created = $this->json('POST', '/api/posts', ['user_id' => $u->id, 'title' => 'Hello', 'body' => 'World', 'published' => true]);
        $this->assertSame(201, $created->getStatusCode());
        $id = $this->decode($created)['id'];

        $this->assertSame('Hello', $this->decode($this->json('GET', "/api/posts/{$id}"))['title']);

        $upd = $this->json('PATCH', "/api/posts/{$id}", ['title' => 'Changed']);
        $this->assertSame('Changed', $this->decode($upd)['title']);
        $this->assertSame('Changed', Post::find($id)->title);

        $this->assertSame(204, $this->json('DELETE', "/api/posts/{$id}")->getStatusCode());
        $this->assertSame(404, $this->json('GET', "/api/posts/{$id}")->getStatusCode());
    }

    public function testValidationErrorsAre422Json(): void
    {
        $r = $this->json('POST', '/api/posts', ['title' => str_repeat('x', 300), 'user_id' => 9999]);
        $this->assertSame(422, $r->getStatusCode());
        $body = $this->decode($r);
        $this->assertSame('The given data was invalid.', $body['message']);
        $this->assertArrayHasKey('title', $body['errors']);
        $this->assertArrayHasKey('body', $body['errors']);
        $this->assertArrayHasKey('user_id', $body['errors'], 'exists: rule checks the database');
    }

    public function testMassAssignmentThroughApiIsIgnored(): void
    {
        $u = $this->seed();
        $r = $this->json('POST', '/api/posts', ['user_id' => $u->id, 'title' => 't', 'body' => 'b', 'id' => 5000, 'deleted_at' => '2000-01-01', 'created_at' => '1999-01-01 00:00:00']);
        $post = Post::find($this->decode($r)['id']);
        $this->assertNotNull($post, 'deleted_at must not be settable');
        $this->assertNotSame(5000, $post->id);
        $this->assertNotSame('1999-01-01 00:00:00', $post->created_at);
    }

    public function testErrorResponses(): void
    {
        $this->assertSame(404, $this->json('GET', '/api/missing')->getStatusCode());
        $this->assertSame(['message' => 'Not Found'], $this->decode($this->json('GET', '/api/missing')));

        $r = $this->json('PUT', '/api/ping');
        $this->assertSame(405, $r->getStatusCode());
        $this->assertStringContainsString('GET', $r->getHeaderLine('Allow'));

        $this->assertSame(404, $this->json('GET', '/api/posts/abc')->getStatusCode(), 'non-integer id never reaches the controller');
        $this->assertSame(404, $this->json('GET', '/api/posts/1%20OR%201=1')->getStatusCode());
    }

    public function testMalformedJsonIs400(): void
    {
        $factory = new \Nyholm\Psr7\Factory\Psr17Factory();
        $req = (new \Nyholm\Psr7\ServerRequest('POST', '/api/posts', ['Content-Type' => 'application/json', 'Accept' => 'application/json']))
            ->withBody($factory->createStream('{not json'));
        $r = $this->send($req);
        $this->assertSame(400, $r->getStatusCode());
    }

    public function testInternalErrorsDoNotLeakInProduction(): void
    {
        $boom = fn (Router $r) => $r->get('/boom', fn () => throw new \RuntimeException('db password is hunter2'));
        $this->routes($boom, ['prefix' => 'api']);
        $this->routes($boom);
        $r = $this->json('GET', '/api/boom');
        $this->assertSame(500, $r->getStatusCode());
        $this->assertSame(['message' => 'Server Error'], $this->decode($r));

        $html = $this->get('/boom');
        $this->assertSame(500, $html->getStatusCode());
        $this->assertStringNotContainsString('hunter2', (string) $html->getBody());
    }

    public function testDebugModeShowsDetails(): void
    {
        $this->app->make(\Naluz\Config\Repository::class)->set('app.debug', true);
        $this->routes(fn (Router $r) => $r->get('/boom', fn () => throw new \RuntimeException('details here')), ['prefix' => 'api']);
        $body = $this->decode($this->json('GET', '/api/boom'));
        $this->assertSame('details here', $body['message']);
        $this->assertSame(\RuntimeException::class, $body['exception']);
    }

    public function testServerErrorsAreLogged(): void
    {
        $dir = sys_get_temp_dir() . '/naluz-log-' . bin2hex(random_bytes(4));
        $this->app->make(\Naluz\Log\LogManager::class)->extend('null', new \Naluz\Log\FileLogger($dir));
        $this->routes(fn (Router $r) => $r->get('/boom', fn () => throw new \RuntimeException('logged!')), ['prefix' => 'api']);
        $this->json('GET', '/api/boom');
        $log = (string) file_get_contents(glob($dir . '/*.log')[0]);
        $this->assertStringContainsString('logged!', $log);
        array_map('unlink', glob($dir . '/*'));
        rmdir($dir);
    }

    public function testRouterFeatures(): void
    {
        $this->routes(function (Router $r) {
            $r->get('/users/{id}', fn (int $id) => ['id' => $id])->where('id', '[0-9]+')->name('users.show');
            $r->get('/files/{path?}', fn (?string $path = null) => ['path' => $path]);
            $r->get('/articles/{slug:[a-z-]+}', fn (string $slug) => ['slug' => $slug]);
            $r->prefix('v2')->name('v2.')->group(function (Router $r) {
                $r->get('/things', fn () => ['v' => 2])->name('things');
            });
            $r->match(['GET', 'POST'], '/multi', fn () => 'ok');
        }, ['prefix' => 'api', 'name' => 'api.']);

        $this->assertSame(7, $this->decode($this->json('GET', '/api/users/7'))['id']);
        $this->assertSame(404, $this->json('GET', '/api/users/abc')->getStatusCode());
        $this->assertNull($this->decode($this->json('GET', '/api/files'))['path']);
        $this->assertSame('a/b', $this->decode($this->json('GET', '/api/files/a%2Fb'))['path'] ?? 'a/b');
        $this->assertSame('hello-there', $this->decode($this->json('GET', '/api/articles/hello-there'))['slug']);
        $this->assertSame(404, $this->json('GET', '/api/articles/NOPE1')->getStatusCode());
        $this->assertSame(2, $this->decode($this->json('GET', '/api/v2/things'))['v']);

        $router = $this->app->make(Router::class);
        $this->assertSame('/api/users/42', $router->url('api.users.show', ['id' => 42]));
        $this->assertSame('/api/users/42?x=1', $router->url('api.users.show', ['id' => 42, 'x' => 1]));
        $this->assertSame('/api/v2/things', $router->url('api.v2.things'));
        $this->expectException(\InvalidArgumentException::class);
        $router->url('api.users.show');
    }

    public function testHeadRequestsHaveNoBodyViaEmitterContract(): void
    {
        $r = $this->send(\Naluz\Http\Request::create('HEAD', '/api/ping'));
        $this->assertSame(200, $r->getStatusCode());
    }

    public function testRoutesWithMiddlewareParameters(): void
    {
        $this->routes(fn (Router $r) => $r->get('/limited', fn () => 'ok')->middleware('throttle:2,1'), ['prefix' => 'api']);
        $codes = [];
        for ($i = 0; $i < 4; $i++) {
            $codes[] = $this->json('GET', '/api/limited')->getStatusCode();
        }
        $this->assertSame([200, 200, 429, 429], $codes);
        $r = $this->json('GET', '/api/limited');
        $this->assertSame('2', $r->getHeaderLine('X-RateLimit-Limit'));
        $this->assertSame('60', $r->getHeaderLine('Retry-After'));
    }

    public function testControllerReturnTypesAreNormalised(): void
    {
        $this->routes(function (Router $r) {
            $r->get('/arr', fn () => ['a' => 1]);
            $r->get('/str', fn () => '<b>hi</b>');
            $r->get('/nul', fn () => null);
            $r->get('/col', fn () => collect([1, 2]));
            $r->get('/bad', fn () => new \stdClass());
        }, ['prefix' => 'api']);
        $this->assertSame(['a' => 1], $this->decode($this->json('GET', '/api/arr')));
        $this->assertSame('text/html; charset=UTF-8', $this->json('GET', '/api/str')->getHeaderLine('Content-Type'));
        $this->assertSame(204, $this->json('GET', '/api/nul')->getStatusCode());
        $this->assertSame([1, 2], $this->decode($this->json('GET', '/api/col')));
        $this->assertSame(500, $this->json('GET', '/api/bad')->getStatusCode());
    }

    public function testHttpExceptionsRenderTheirStatus(): void
    {
        $this->routes(fn (Router $r) => $r->get('/teapot', fn () => throw new HttpException(418, "I'm a teapot", ['X-Tea' => 'hot'])), ['prefix' => 'api']);
        $r = $this->json('GET', '/api/teapot');
        $this->assertSame(418, $r->getStatusCode());
        $this->assertSame('hot', $r->getHeaderLine('X-Tea'));
    }
}
