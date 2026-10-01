<?php

declare(strict_types=1);

namespace Naluz\Tests\Security;

use Naluz\Redis\Client;
use Naluz\Redis\RedisCache;
use Naluz\Redis\RedisException;
use Naluz\Redis\RedisSessionHandler;
use Naluz\Session\Store;
use Naluz\Tests\Support\RedisServer;
use PHPUnit\Framework\TestCase;

final class RedisTest extends TestCase
{
    use RedisServer;

    public function testClientBasics(): void
    {
        $r = $this->redis();
        $this->assertSame('OK', $r->command('SET', 'a', 'héllo wörld'));
        $this->assertSame('héllo wörld', $r->get('a'));
        $this->assertNull($r->get('missing'));
        $this->assertSame(5, $r->command('RPUSH', 'l', 1, 2, 3, 4, 5));
        $this->assertSame(['1', '2', '3'], $r->command('LRANGE', 'l', 0, 2));
        $this->assertTrue($r->exists('a'));
        $this->assertSame(1, $r->del('a'));
        $this->assertSame(str_repeat('x', 200_000), (function ($r) {
            $r->set('big', str_repeat('x', 200_000));
            return $r->get('big');
        })($r), 'large payloads round-trip');
    }

    public function testServerErrorsBecomeExceptionsAndConnectionSurvives(): void
    {
        $r = $this->redis();
        try {
            $r->command('NOPE');
            $this->fail();
        } catch (RedisException $e) {
            $this->assertStringContainsString('Redis error:', $e->getMessage());
        }
        $this->assertSame('PONG', $r->command('PING'));
    }

    public function testCommandInjectionIsImpossibleBecauseArgumentsAreLengthPrefixed(): void
    {
        $r = $this->redis();
        $r->set('k', "v\r\nFLUSHALL\r\n");
        $r->set('keep', '1');
        $this->assertSame("v\r\nFLUSHALL\r\n", $r->get('k'));
        $this->assertSame('1', $r->get('keep'));
    }

    public function testReconnectsAfterDroppedConnection(): void
    {
        $r = $this->redis();
        $this->assertSame('PONG', $r->command('PING'));
        $r->command('CLIENT', 'KILL', 'TYPE', 'normal', 'SKIPME', 'no'); // kills our own connection
        try {
            $r->command('PING');
        } catch (RedisException) {
            // the reply to CLIENT KILL itself may fail; the next call must reconnect
        }
        $this->assertSame('PONG', $r->command('PING'));
    }

    public function testUnreachableServerThrows(): void
    {
        $this->expectException(RedisException::class);
        (new Client('127.0.0.1', 1, timeout: 0.2))->command('PING');
    }

    public function testRedisCache(): void
    {
        $cache = new RedisCache($this->redis());
        $this->assertNull($cache->get('x'));
        $cache->set('x', ['a' => [1, 2]], 60);
        $this->assertSame(['a' => [1, 2]], $cache->get('x'));
        $this->assertTrue($cache->has('x'));
        $cache->set('ttl', 1, new \DateInterval('PT30S'));
        $this->assertSame(1, $cache->get('ttl'));
        $cache->set('gone', 1, -5);
        $this->assertNull($cache->get('gone'));
        $cache->delete('x');
        $this->assertFalse($cache->has('x'));

        $this->assertSame(1, $cache->increment('hits', 60));
        $this->assertSame(2, $cache->increment('hits', 60));
        $cache->clear();
        $this->assertNull($cache->get('ttl'));
    }

    public function testRedisCacheNeverUnserializesObjects(): void
    {
        $redis = $this->redis();
        $cache = new RedisCache($redis);
        $redis->set('naluz:cache:evil', serialize([new \ArrayObject(['pwn']), null]));
        $this->assertNotInstanceOf(\ArrayObject::class, $cache->get('evil'));
    }

    public function testRedisSessionHandlerAndStore(): void
    {
        $handler = new RedisSessionHandler($this->redis(), 60);
        $store = new Store($handler);
        $store->start();
        $store->put('user', 5);
        $store->save();
        $id = $store->id();

        $again = new Store($handler);
        $again->start($id);
        $this->assertSame(5, $again->get('user'));
        $again->regenerate();
        $this->assertNotSame($id, $again->id());
        $third = new Store($handler);
        $third->start($id);
        $this->assertNull($third->get('user'), 'old id destroyed after regenerate');

        $this->assertSame('', $handler->read('../../evil'));
        $this->assertFalse($handler->write('bad id', 'x'));
    }
}
