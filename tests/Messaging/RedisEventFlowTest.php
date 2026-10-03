<?php

declare(strict_types=1);

namespace Naluz\Tests\Messaging;

use Naluz\Config\Repository;
use Naluz\Messaging\BrokerManager;
use Naluz\Messaging\Consumer;
use Naluz\Messaging\EventBus;
use Naluz\Tests\Support\RedisServer;
use Naluz\Tests\TestCase;

/** The whole path — config → BrokerManager → EventBus → Redis Stream → Consumer → Subscriber — against a real redis-server. */
final class RedisEventFlowTest extends TestCase
{
    use RedisServer;

    protected function configOverrides(): array
    {
        return parent::configOverrides() + [
            'messaging.default' => 'redis',
            'messaging.connections.redis' => ['driver' => 'redis', 'host' => '127.0.0.1', 'port' => self::redisPort(), 'prefix' => 'flow:', 'consumer' => 'c1'],
            'messaging.subscribers' => ['orders.placed' => [FailingSubscriber::class]],
            'messaging.signing_key' => 'flow-key',
        ];
    }

    public function testSignedEventsFlowThroughRedisWithRetryAndDeadLetter(): void
    {
        $this->redis(); // starts/flushes the server
        Recorder::$log = [];
        Recorder::$failures = 1;
        $app = $this->app;
        $app->make(Repository::class)->set('messaging.connections.redis.port', self::redisPort());
        $app->singleton(BrokerManager::class, fn ($c) => new BrokerManager($c, $c->make(Repository::class)));
        $app->singleton(EventBus::class, fn ($c) => new EventBus($c->make(BrokerManager::class), new \Naluz\Messaging\Codec('flow-key'), $c->make(Repository::class)));
        $app->singleton(Consumer::class, fn ($c) => new Consumer($c, $c->make(BrokerManager::class), $c->make(EventBus::class), new \Naluz\Messaging\Codec('flow-key'), $c->make(Repository::class), $c->make(\Psr\Log\LoggerInterface::class)));

        $app->make(BrokerManager::class)->connection()->declare(['orders.placed', 'orders.placed.dlq'], 'billing');
        $app->make(EventBus::class)->publish('orders.placed', ['id' => 42]);
        $consumer = $app->make(Consumer::class);

        $this->assertSame('retried', $consumer->consumeOne(['orders.placed'], 'billing', null, 200, 3));
        $this->assertSame('processed', $consumer->consumeOne(['orders.placed'], 'billing', null, 200, 3));
        $this->assertSame(['recovered:a2'], Recorder::$log);
        $this->assertNull($consumer->consumeOne(['orders.placed'], 'billing', null, 100, 3));

        Recorder::$failures = 99;
        $app->make(EventBus::class)->publish('orders.placed', ['id' => 43]);
        $results = [];
        while (($r = $consumer->consumeOne(['orders.placed'], 'billing', null, 200, 2)) !== null) {
            $results[] = $r;
        }
        $this->assertSame(['retried', 'dead'], $results);
        $this->assertNotNull($app->make(BrokerManager::class)->connection()->receive(['orders.placed.dlq'], 'billing', 200), 'dead letter published');
    }
}
