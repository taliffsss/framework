<?php

declare(strict_types=1);

namespace Naluz\Tests\Http;

use Naluz\Routing\Router;
use Naluz\Tests\TestCase;
use Naluz\Validation\Validator;

final class WebTest extends TestCase
{
    public function testHomePageRendersViewWithLayout(): void
    {
        $r = $this->get('/');
        $html = (string) $r->getBody();
        $this->assertSame(200, $r->getStatusCode());
        $this->assertStringContainsString('<!doctype html>', $html);
        $this->assertStringContainsString('Welcome to NaluzPHP', $html);
        $this->assertStringContainsString('<title>NaluzPHP</title>', $html);
    }

    public function testNotFoundPageIsHtmlForBrowsers(): void
    {
        $r = $this->get('/nope', ['Accept' => 'text/html']);
        $this->assertSame(404, $r->getStatusCode());
        $this->assertStringContainsString('text/html', $r->getHeaderLine('Content-Type'));
        $this->assertStringContainsString('404', (string) $r->getBody());
    }

    public function testViewsEscapeWhenUsingE(): void
    {
        $this->routes(fn (Router $r) => $r->get('/hello', fn () => view('home', ['name' => '<script>alert(1)</script>'])));
        $html = (string) $this->get('/hello')->getBody();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
    }

    public function testErrorPagesEscapeMessages(): void
    {
        $this->routes(fn (Router $r) => $r->get('/x', fn () => throw new \Naluz\Http\HttpException(400, '<img src=x onerror=alert(1)>')));
        $this->assertStringNotContainsString('<img', (string) $this->get('/x', ['Accept' => 'text/html'])->getBody());
    }

    public function testViewNamesCannotTraverse(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        view('../../.env');
    }

    public function testFormMethodOverride(): void
    {
        $this->routes(function (Router $r) {
            $r->delete('/things/{id}', fn (int $id) => "deleted {$id}");
            $r->post('/things/{id}', fn (int $id) => "posted {$id}");
        });
        $token = $this->tokenFromSession();
        $r = $this->form('POST', '/things/3', ['_method' => 'DELETE', '_token' => $token]);
        $this->assertSame('deleted 3', (string) $r->getBody());
        $r = $this->form('POST', '/things/3', ['_method' => 'TRACE', '_token' => $token]);
        $this->assertSame('posted 3', (string) $r->getBody(), 'only PUT/PATCH/DELETE are honoured');
    }

    public function testValidationFailureRedirectsBackWithFlashedErrorsAndOldInput(): void
    {
        $this->routes(function (Router $r) {
            $r->get('/form', fn () => ['errors' => app(\Naluz\Session\Store::class)->get('errors'), 'old' => app(\Naluz\Session\Store::class)->get('old')]);
            $r->post('/form', function (\Psr\Http\Message\ServerRequestInterface $req) {
                Validator::make(\Naluz\Http\Request::input($req), ['email' => 'required|email', 'password' => 'required|min:8'])->validate();
                return 'ok';
            });
        });
        $token = $this->tokenFromSession();
        $r = $this->form('POST', 'http://localhost/form', ['email' => 'nope', 'password' => 'short', '_token' => $token], ['Referer' => 'http://localhost/form?step=2']);
        $this->assertSame(303, $r->getStatusCode());
        $this->assertSame('/form?step=2', $r->getHeaderLine('Location'));

        $page = json_decode((string) $this->get('/form')->getBody(), true);
        $this->assertArrayHasKey('email', $page['errors']);
        $this->assertSame('nope', $page['old']['email']);
        $this->assertArrayNotHasKey('password', $page['old'], 'passwords are never flashed back');
        $this->assertArrayNotHasKey('_token', $page['old']);

        $again = json_decode((string) $this->get('/form')->getBody(), true);
        $this->assertNull($again['errors'], 'flash data lives for one request only');
    }

    /** Establish a session by hitting a page, then read the CSRF token out of it. */
    private function tokenFromSession(): string
    {
        $this->routes(fn (Router $r) => $r->get('/_token', fn () => csrf_token()));
        return (string) $this->get('/_token')->getBody();
    }
}
