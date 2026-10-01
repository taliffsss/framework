<?php

declare(strict_types=1);

namespace Naluz\Tests\Unit;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Naluz\Http\Client\Http;
use Naluz\Http\Client\HttpClientFactory;
use Naluz\Http\Client\PrivateNetworkGuard;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;

final class HttpClientTest extends TestCase
{
    /** @var list<array{request:\Psr\Http\Message\RequestInterface}> */
    private array $history = [];

    private function http(array $responses, array $config = [], int $redirects = 3): Http
    {
        $this->history = [];
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));
        $f = new Psr17Factory();
        return new Http(HttpClientFactory::make(['handler' => $stack] + $config), $f, $f, $redirects);
    }

    public function testGetWithQueryAndHeaders(): void
    {
        $http = $this->http([new GuzzleResponse(200, [], '{"ok":true}')]);
        $res = $http->get('https://8.8.8.8/users', ['page' => 2, 'tags' => ['a', 'b']], ['Authorization' => 'Bearer t']);
        $this->assertSame(200, $res->getStatusCode());
        $this->assertSame(['ok' => true], Http::json($res));
        $req = $this->history[0]['request'];
        $this->assertSame('GET', $req->getMethod());
        $this->assertSame('page=2&tags%5B0%5D=a&tags%5B1%5D=b', $req->getUri()->getQuery());
        $this->assertSame('Bearer t', $req->getHeaderLine('Authorization'));
        $this->assertSame('NaluzPHP', $req->getHeaderLine('User-Agent'));
    }

    public function testPostSendsJson(): void
    {
        $http = $this->http([new GuzzleResponse(201, [], '{"id":5}')]);
        $res = $http->post('https://8.8.8.8/users', ['name' => 'Ann "A"']);
        $this->assertSame(201, $res->getStatusCode());
        $req = $this->history[0]['request'];
        $this->assertSame('application/json', $req->getHeaderLine('Content-Type'));
        $this->assertSame('{"name":"Ann \"A\""}', (string) $req->getBody());
    }

    public function testErrorStatusesAreResponsesNotExceptions(): void
    {
        $http = $this->http([new GuzzleResponse(404), new GuzzleResponse(500, [], 'boom')]);
        $this->assertSame(404, $http->get('https://8.8.8.8/x')->getStatusCode());
        $this->assertSame(500, $http->delete('https://8.8.8.8/x')->getStatusCode());
    }

    public function testNetworkFailuresThrowPsr18Exceptions(): void
    {
        $http = $this->http([new \GuzzleHttp\Exception\ConnectException('down', new \GuzzleHttp\Psr7\Request('GET', 'https://8.8.8.8'))]);
        $this->expectException(ClientExceptionInterface::class);
        $http->get('https://8.8.8.8/x');
    }

    public function testHeaderInjectionIsRefused(): void
    {
        $http = $this->http([new GuzzleResponse(200)]);
        $this->expectException(\InvalidArgumentException::class);
        $http->get('https://8.8.8.8/x', [], ["X-A" => "v\r\nX-Evil: 1"]);
    }

    // ---------------------------------------------------------------- SSRF guard

    #[DataProvider('privateTargets')]
    public function testPrivateAndReservedTargetsAreBlocked(string $url): void
    {
        $http = $this->http([new GuzzleResponse(200, [], 'secret')]);
        try {
            $http->get($url);
            $this->fail("request to {$url} was allowed");
        } catch (ClientExceptionInterface $e) {
            $this->assertStringContainsString('Blocked request', $e->getMessage());
        }
        $this->assertSame([], $this->history, 'nothing reached the network layer');
    }

    public static function privateTargets(): array
    {
        return [
            ['http://127.0.0.1/admin'], ['http://localhost/admin'], ['http://10.0.0.5/'], ['http://172.16.0.1/'], ['http://192.168.1.1/'],
            ['http://169.254.169.254/latest/meta-data/'], ['http://0.0.0.0/'], ['http://[::1]/'], ['http://[::ffff:127.0.0.1]/'],
            ['http://[fe80::1]/'], ['http://[fd00::1]/'], ['http://100.64.0.1/'] ,
        ];
    }

    public function testNonHttpSchemesAreBlocked(): void
    {
        $this->expectException(ClientExceptionInterface::class);
        $this->http([new GuzzleResponse(200)])->get('file:///etc/passwd');
    }

    public function testRedirectsToPrivateAddressesAreBlockedAtEveryHop(): void
    {
        $http = $this->http([
            new GuzzleResponse(302, ['Location' => 'http://169.254.169.254/latest/meta-data/']),
            new GuzzleResponse(200, [], 'METADATA'),
        ]);
        try {
            $http->get('https://8.8.8.8/redirect-me');
            $this->fail('redirect into the metadata service was followed');
        } catch (ClientExceptionInterface $e) {
            $this->assertStringContainsString('Blocked request', $e->getMessage());
        }
        $this->assertCount(1, $this->history, 'only the first (public) hop was sent');
    }

    public function testRedirectsAreFollowedAndRelativeLocationsResolved(): void
    {
        $http = $this->http([new GuzzleResponse(301, ['Location' => '/new/path?x=1']), new GuzzleResponse(200, [], 'arrived')]);
        $res = $http->get('https://8.8.8.8/old');
        $this->assertSame('arrived', (string) $res->getBody());
        $this->assertSame('https://8.8.8.8/new/path?x=1', (string) $this->history[1]['request']->getUri());
    }

    public function testCredentialsAreNotForwardedToAnotherOrigin(): void
    {
        $http = $this->http([new GuzzleResponse(302, ['Location' => 'https://8.8.4.4/x']), new GuzzleResponse(200)]);
        $http->get('https://8.8.8.8/a', [], ['Authorization' => 'Bearer secret', 'Cookie' => 's=1', 'X-Keep' => 'y']);
        $second = $this->history[1]['request'];
        $this->assertFalse($second->hasHeader('Authorization'));
        $this->assertFalse($second->hasHeader('Cookie'));
        $this->assertSame('y', $second->getHeaderLine('X-Keep'));
    }

    public function testCredentialsAreKeptOnSameOriginRedirects(): void
    {
        $http = $this->http([new GuzzleResponse(302, ['Location' => '/b']), new GuzzleResponse(200)]);
        $http->get('https://8.8.8.8/a', [], ['Authorization' => 'Bearer secret']);
        $this->assertSame('Bearer secret', $this->history[1]['request']->getHeaderLine('Authorization'));
    }

    public function testHttpsToHttpDowngradeDropsCredentials(): void
    {
        $http = $this->http([new GuzzleResponse(302, ['Location' => 'http://8.8.8.8/a']), new GuzzleResponse(200)]);
        $http->get('https://8.8.8.8/a', [], ['Authorization' => 'Bearer secret']);
        $this->assertFalse($this->history[1]['request']->hasHeader('Authorization'));
    }

    public function testRedirectMethodSemantics(): void
    {
        $http = $this->http([new GuzzleResponse(302, ['Location' => '/b']), new GuzzleResponse(200)]);
        $http->post('https://8.8.8.8/a', ['k' => 'v']);
        $this->assertSame('GET', $this->history[1]['request']->getMethod(), '302 turns POST into GET');
        $this->assertSame('', (string) $this->history[1]['request']->getBody());

        $http = $this->http([new GuzzleResponse(307, ['Location' => '/b']), new GuzzleResponse(200)]);
        $http->post('https://8.8.8.8/a', ['k' => 'v']);
        $this->assertSame('POST', $this->history[1]['request']->getMethod(), '307 keeps the method');
        $this->assertSame('{"k":"v"}', (string) $this->history[1]['request']->getBody());
    }

    public function testRedirectLoopsAreBounded(): void
    {
        $loop = array_fill(0, 10, new GuzzleResponse(302, ['Location' => '/again']));
        $http = $this->http($loop, [], 3);
        try {
            $http->get('https://8.8.8.8/a');
            $this->fail();
        } catch (\Naluz\Http\Client\TooManyRedirectsException $e) {
            $this->assertInstanceOf(\Psr\Http\Client\RequestExceptionInterface::class, $e);
        }
        $this->assertCount(4, $this->history, '1 request + 3 redirects');
    }

    public function testRedirectFollowingCanBeDisabled(): void
    {
        $http = $this->http([new GuzzleResponse(302, ['Location' => '/b'])], [], 0);
        $this->assertSame(302, $http->get('https://8.8.8.8/a')->getStatusCode());
    }

    public function testGuardCanBeDisabledForTrustedInternalCalls(): void
    {
        $http = $this->http([new GuzzleResponse(200, [], 'internal')], ['allow_private_networks' => true]);
        $this->assertSame('internal', (string) $http->get('http://10.0.0.5/health')->getBody());
    }

    public function testPublicAddressClassification(): void
    {
        foreach (['8.8.8.8', '1.1.1.1', '93.184.216.34', '2606:4700:4700::1111'] as $ip) {
            $this->assertTrue(PrivateNetworkGuard::isPublic($ip), $ip);
        }
        foreach (['127.0.0.1', '10.1.2.3', '192.168.0.1', '169.254.1.1', '::1', 'fe80::1', '::ffff:10.0.0.1', '0.0.0.0', '240.0.0.1'] as $ip) {
            $this->assertFalse(PrivateNetworkGuard::isPublic($ip), $ip);
        }
    }
}
