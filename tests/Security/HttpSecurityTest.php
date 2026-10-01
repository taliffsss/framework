<?php

declare(strict_types=1);

namespace Naluz\Tests\Security;

use App\Models\User;
use Naluz\Auth\Auth;
use Naluz\Routing\Router;
use Naluz\Security\Jwt;
use Naluz\Session\Store;
use Naluz\Tests\TestCase;

final class HttpSecurityTest extends TestCase
{
    private function csrfRoutes(): void
    {
        $this->routes(function (Router $r) {
            $r->get('/token', fn () => csrf_token());
            $r->post('/transfer', fn () => 'transferred');
            $r->delete('/account', fn () => 'deleted');
        });
    }

    // ------------------------------------------------------------------ headers

    public function testSecurityHeadersAreAppliedToEveryResponse(): void
    {
        foreach ([$this->get('/'), $this->json('GET', '/api/ping'), $this->get('/missing')] as $r) {
            $this->assertSame('nosniff', $r->getHeaderLine('X-Content-Type-Options'));
            $this->assertSame('SAMEORIGIN', $r->getHeaderLine('X-Frame-Options'));
            $this->assertStringContainsString("frame-ancestors 'self'", $r->getHeaderLine('Content-Security-Policy'));
            $this->assertSame('strict-origin-when-cross-origin', $r->getHeaderLine('Referrer-Policy'));
        }
    }

    public function testHstsOnlyOverHttps(): void
    {
        $this->assertFalse($this->get('http://localhost/')->hasHeader('Strict-Transport-Security'));
        $this->assertTrue($this->get('https://localhost/')->hasHeader('Strict-Transport-Security'));
    }

    public function testHeadersAreConfigurable(): void
    {
        $this->app->make(\Naluz\Config\Repository::class)->set('security.headers', ['Content-Security-Policy' => "default-src 'none'", 'X-Frame-Options' => null]);
        $r = $this->get('/');
        $this->assertSame("default-src 'none'", $r->getHeaderLine('Content-Security-Policy'));
        $this->assertFalse($r->hasHeader('X-Frame-Options'));
    }

    // ------------------------------------------------------------------ CSRF

    public function testStateChangingRequestsNeedACsrfToken(): void
    {
        $this->csrfRoutes();
        $token = (string) $this->get('/token')->getBody();
        $this->assertSame(64, strlen($token));

        $this->assertSame(419, $this->form('POST', '/transfer')->getStatusCode(), 'no token');
        $this->assertSame(419, $this->form('POST', '/transfer', ['_token' => 'forged'])->getStatusCode(), 'wrong token');
        $this->assertSame(419, $this->form('DELETE', '/account')->getStatusCode());
        $this->assertSame(200, $this->form('POST', '/transfer', ['_token' => $token])->getStatusCode(), 'valid body token');
        $this->assertSame(200, $this->form('POST', '/transfer', [], ['X-CSRF-TOKEN' => $token])->getStatusCode(), 'valid header token');
        $this->assertSame(200, $this->get('/token')->getStatusCode(), 'GET is safe');
    }

    public function testCsrfTokenFromAnotherSessionIsRejected(): void
    {
        $this->csrfRoutes();
        $victimToken = (string) $this->get('/token')->getBody();
        $this->cookies = []; // attacker has their own, different session
        $this->get('/token');
        $this->assertSame(419, $this->form('POST', '/transfer', ['_token' => $victimToken])->getStatusCode());
    }

    public function testCsrfRejectsCrossOriginEvenWithToken(): void
    {
        $this->csrfRoutes();
        $token = (string) $this->get('/token')->getBody();
        $same = $this->form('POST', 'http://localhost/transfer', ['_token' => $token], ['Origin' => 'http://localhost']);
        $this->assertSame(200, $same->getStatusCode());
        $evil = $this->form('POST', 'http://localhost/transfer', ['_token' => $token], ['Origin' => 'http://evil.example']);
        $this->assertSame(419, $evil->getStatusCode());
    }

    public function testApiRoutesAreNotCsrfProtectedBecauseTheyHoldNoCookies(): void
    {
        $this->assertNotSame(419, $this->json('POST', '/api/posts', [])->getStatusCode());
    }

    // ------------------------------------------------------------------ sessions

    public function testSessionCookieFlags(): void
    {
        $cookie = $this->get('/')->getHeaderLine('Set-Cookie');
        $this->assertStringContainsString('naluz_session=', $cookie);
        $this->assertStringContainsString('HttpOnly', $cookie);
        $this->assertStringContainsString('SameSite=Lax', $cookie);
        $this->assertStringNotContainsString('Secure', $cookie);
        $this->assertStringContainsString('Secure', $this->get('https://localhost/')->getHeaderLine('Set-Cookie'));
    }

    public function testSessionFixationIsPrevented(): void
    {
        $attackerChosen = str_repeat('a', 40);
        $this->cookies = ['naluz_session' => $attackerChosen];
        $r = $this->get('/');
        $this->assertStringNotContainsString($attackerChosen, $r->getHeaderLine('Set-Cookie'), 'unknown client-supplied ids are replaced');
    }

    public function testMalformedSessionIdsAreIgnored(): void
    {
        $this->cookies = ['naluz_session' => '../../etc/passwd'];
        $this->assertSame(200, $this->get('/')->getStatusCode());
    }

    public function testLoginRegeneratesSessionId(): void
    {
        $this->routes(function (Router $r) {
            $r->post('/login', fn () => ['ok' => app(Auth::class)->attempt(['email' => 'a@x.io', 'password' => 'pw'])]);
            $r->get('/me', fn () => ['id' => app(Auth::class)->id()]);
        });
        User::create(['name' => 'A', 'email' => 'a@x.io', 'password' => 'pw']);
        $token = $this->token();
        $before = $this->cookies['naluz_session'];
        $r = $this->form('POST', '/login', ['_token' => $token]);
        $this->assertTrue(json_decode((string) $r->getBody(), true)['ok']);
        $this->assertNotSame($before, $this->cookies['naluz_session'], 'session id changes on login');
        $this->assertSame(1, json_decode((string) $this->get('/me')->getBody(), true)['id']);
    }

    private function token(): string
    {
        $this->routes(fn (Router $r) => $r->get('/tok', fn () => csrf_token()));
        return (string) $this->get('/tok')->getBody();
    }

    // ------------------------------------------------------------------ auth

    public function testAuthAttemptAndMiddleware(): void
    {
        $this->routes(function (Router $r) {
            $r->get('/dashboard', fn () => 'secret')->middleware('auth');
            $r->post('/login', fn (\Psr\Http\Message\ServerRequestInterface $req) => ['ok' => app(Auth::class)->attempt(\Naluz\Http\Request::input($req))]);
            $r->post('/logout', function () {
                app(Auth::class)->logout();
                return 'bye';
            });
        });
        $this->routes(fn (Router $r) => $r->get('/secret', fn () => ['s' => 1])->middleware('auth'), ['prefix' => 'api']);
        User::create(['name' => 'A', 'email' => 'a@x.io', 'password' => 'pw']);

        $guest = $this->get('/dashboard');
        $this->assertSame(302, $guest->getStatusCode());
        $this->assertSame('/login', $guest->getHeaderLine('Location'));
        $this->assertSame(401, $this->json('GET', '/api/secret')->getStatusCode());

        $token = $this->token();
        $login = fn (array $c) => json_decode((string) $this->form('POST', '/login', $c + ['_token' => $token])->getBody(), true)['ok'];

        $this->assertFalse($login(['email' => 'a@x.io', 'password' => 'wrong']));
        $this->assertFalse($login(['email' => 'nobody@x.io', 'password' => 'pw']));
        $this->assertFalse($login(['email' => ['$ne' => ''], 'password' => 'pw']), 'array injection in credentials');
        $this->assertSame(302, $this->get('/dashboard')->getStatusCode());

        $this->assertTrue($login(['email' => 'a@x.io', 'password' => 'pw']));
        $this->assertSame('secret', (string) $this->get('/dashboard')->getBody());

        // logout needs the (new) session's CSRF token
        $this->routes(fn (Router $r) => $r->get('/tok2', fn () => csrf_token()));
        $this->form('POST', '/logout', ['_token' => (string) $this->get('/tok2')->getBody()]);
        $this->assertSame(302, $this->get('/dashboard')->getStatusCode());
    }

    // ------------------------------------------------------------------ JWT

    public function testJwtMiddleware(): void
    {
        $this->routes(fn (Router $r) => $r->get('/me', fn (\Psr\Http\Message\ServerRequestInterface $req) => ['id' => $req->getAttribute('auth.id')])->middleware('jwt'), ['prefix' => 'api']);
        $jwt = $this->app->make(Jwt::class);

        $this->assertSame(401, $this->json('GET', '/api/me')->getStatusCode());
        $this->assertSame(401, $this->json('GET', '/api/me', [], ['Authorization' => 'Bearer nonsense'])->getStatusCode());
        $this->assertSame(401, $this->json('GET', '/api/me', [], ['Authorization' => 'Bearer ' . $jwt->encode(['sub' => 1], -100)])->getStatusCode());
        $this->assertSame(401, $this->json('GET', '/api/me', [], ['Authorization' => 'Basic abc'])->getStatusCode());
        $ok = $this->json('GET', '/api/me', [], ['Authorization' => 'Bearer ' . $jwt->encode(['sub' => 42])]);
        $this->assertSame(200, $ok->getStatusCode());
        $this->assertSame(42, $this->decode($ok)['id']);
        $this->assertStringContainsString('Bearer', $this->json('GET', '/api/me')->getHeaderLine('WWW-Authenticate'));
    }

    // ------------------------------------------------------------------ CORS

    public function testCorsDisabledByDefault(): void
    {
        $r = $this->json('GET', '/api/ping', [], ['Origin' => 'http://evil.example']);
        $this->assertFalse($r->hasHeader('Access-Control-Allow-Origin'));
    }

    public function testCorsAllowListAndPreflight(): void
    {
        $this->app->make(\Naluz\Config\Repository::class)->set('security.cors.allowed_origins', ['https://app.example']);
        $ok = $this->json('GET', '/api/ping', [], ['Origin' => 'https://app.example']);
        $this->assertSame('https://app.example', $ok->getHeaderLine('Access-Control-Allow-Origin'));
        $this->assertSame('Origin', $ok->getHeaderLine('Vary'));

        $evil = $this->json('GET', '/api/ping', [], ['Origin' => 'https://evil.example']);
        $this->assertFalse($evil->hasHeader('Access-Control-Allow-Origin'));

        $pre = $this->send(\Naluz\Http\Request::create('OPTIONS', '/api/posts', [], ['Origin' => 'https://app.example', 'Access-Control-Request-Method' => 'POST']));
        $this->assertSame(204, $pre->getStatusCode());
        $this->assertStringContainsString('POST', $pre->getHeaderLine('Access-Control-Allow-Methods'));

        $badPre = $this->send(\Naluz\Http\Request::create('OPTIONS', '/api/posts', [], ['Origin' => 'https://evil.example', 'Access-Control-Request-Method' => 'POST']));
        $this->assertSame(403, $badPre->getStatusCode());
    }

    public function testCorsWildcardIsNeverCombinedWithCredentials(): void
    {
        $cfg = $this->app->make(\Naluz\Config\Repository::class);
        $cfg->set('security.cors.allowed_origins', ['*']);
        $r = $this->json('GET', '/api/ping', [], ['Origin' => 'https://any.example']);
        $this->assertSame('*', $r->getHeaderLine('Access-Control-Allow-Origin'));
        $cfg->set('security.cors.supports_credentials', true);
        $r = $this->json('GET', '/api/ping', [], ['Origin' => 'https://any.example']);
        $this->assertSame('https://any.example', $r->getHeaderLine('Access-Control-Allow-Origin'));
        $this->assertNotSame('*', $r->getHeaderLine('Access-Control-Allow-Origin'));
    }

    // ------------------------------------------------------------------ open redirect

    public function testValidationRedirectNeverLeavesTheSite(): void
    {
        $this->routes(function (Router $r) {
            $r->post('/f', function () {
                \Naluz\Validation\Validator::make([], ['x' => 'required'])->validate();
            });
            $r->get('/tok', fn () => csrf_token());
        });
        $token = (string) $this->get('/tok')->getBody();
        foreach (['http://evil.example/steal', '//evil.example', 'javascript:alert(1)'] as $referer) {
            $r = $this->form('POST', 'http://localhost/f', ['_token' => $token], ['Referer' => $referer]);
            $this->assertSame('/', $r->getHeaderLine('Location'), $referer);
        }
    }

    // ------------------------------------------------------------------ misc

    public function testXssPayloadsAreEscaped(): void
    {
        $this->assertSame('&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;', e('<script>alert("x")</script>'));
        $this->assertSame('&#039;&gt;&lt;img src=x onerror=1&gt;', e("'><img src=x onerror=1>"));
    }

    public function testJsonResponsesEscapeHtmlSensitiveCharacters(): void
    {
        $body = (string) json(['x' => '</script><b>&'])->getBody();
        $this->assertStringNotContainsString('</script>', $body);
        $this->assertSame(['x' => '</script><b>&'], json_decode($body, true));
    }

    public function testSessionStoreIsJsonNotPhpSerialised(): void
    {
        $handler = new \Naluz\Session\ArraySessionHandler();
        $store = new Store($handler);
        $store->start();
        $store->put('user', ['id' => 1]);
        $store->save();
        $this->assertJson(array_values($handler->data)[0]);
    }
}
