<?php

declare(strict_types=1);

namespace Naluz\Tests\Messaging;

use Naluz\Messaging\Broker;
use Naluz\Messaging\Brokers\RedisStreamBroker;
use Naluz\Messaging\MessagingException;
use Naluz\Tests\Support\RedisServer;

final class RedisStreamBrokerTest extends BrokerContract
{
    use RedisServer;

    protected function broker(): Broker
    {
        return new RedisStreamBroker($this->redis(), 'test:', 1000, 60, '0', 'c1');
    }

    public function testCrashedConsumersMessageIsReclaimedAfterTheVisibilityTimeout(): void
    {
        $redis = $this->redis();
        $crashed = new RedisStreamBroker($redis, 'test:', 1000, 1, '0', 'dead-consumer');
        $crashed->declare(['t'], 'g');
        $crashed->publish('t', 'work');
        $this->assertSame('work', $crashed->receive(['t'], 'g', 100)?->body); // delivered, never acked
        $alive = new RedisStreamBroker($redis, 'test:', 1000, 1, '0', 'alive-consumer');
        $this->assertNull($alive->receive(['t'], 'g', 100), 'still within the visibility timeout');
        sleep(2);
        $d = $alive->receive(['t'], 'g', 100);
        $this->assertSame('work', $d?->body);
        $d->ack();
        $this->assertNull($alive->receive(['t'], 'g', 100));
    }

    public function testStartIdDollarSkipsHistory(): void
    {
        $redis = $this->redis();
        $b = new RedisStreamBroker($redis, 'test:', 1000, 60, '$', 'c1');
        $b->publish('t', 'old');
        $b->declare(['t'], 'late');
        $this->assertNull($b->receive(['t'], 'late', 100));
        $b->publish('t', 'new');
        $this->assertSame('new', $b->receive(['t'], 'late', 200)?->body);
    }

    public function testStreamIsTrimmedToMaxLength(): void
    {
        $redis = $this->redis();
        $b = new RedisStreamBroker($redis, 'test:', 10, 60, '0', 'c1');
        for ($i = 0; $i < 500; $i++) {
            $b->publish('t', (string) $i);
        }
        $this->assertLessThan(500, (int) $redis->command('XLEN', 'test:t'));
    }

    public function testDeclareIsIdempotent(): void
    {
        $b = $this->broker();
        $b->declare(['t'], 'g');
        $b2 = new RedisStreamBroker($this->redisSame(), 'test:', 1000, 60, '0', 'c2');
        $b2->declare(['t'], 'g'); // BUSYGROUP is swallowed
        $this->addToAssertionCount(1);
    }

    public function testConnectionErrorsBecomeMessagingExceptions(): void
    {
        $b = new RedisStreamBroker(new \Naluz\Redis\Client('127.0.0.1', 1, null, 0, 0.2));
        $this->expectException(MessagingException::class);
        $b->publish('t', 'x');
    }

    private function redisSame(): \Naluz\Redis\Client
    {
        return new \Naluz\Redis\Client('127.0.0.1', self::redisPort());
    }
}
