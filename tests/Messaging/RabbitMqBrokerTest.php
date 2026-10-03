<?php

declare(strict_types=1);

namespace Naluz\Tests\Messaging;

use Naluz\Messaging\Brokers\RabbitMqBroker;
use Naluz\Messaging\MessagingException;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Exception\AMQPConnectionClosedException;
use PhpAmqpLib\Message\AMQPMessage;
use PHPUnit\Framework\TestCase;

/** Exercises the driver against a mocked php-amqplib channel (a real broker is not needed in CI). */
final class RabbitMqBrokerTest extends TestCase
{
    public function testPublishGoesToTheTopicExchangeAsPersistentJson(): void
    {
        $channel = $this->createMock(AMQPChannel::class);
        $channel->expects($this->once())->method('exchange_declare')->with('naluz.events', 'topic', false, true, false);
        $channel->expects($this->once())->method('basic_qos')->with(0, 1, false);
        $channel->expects($this->exactly(2))->method('basic_publish')->willReturnCallback(function (AMQPMessage $m, string $exchange, string $key): void {
            $this->assertSame('naluz.events', $exchange);
            $this->assertSame('orders.placed', $key);
            $this->assertSame('{"a":1}', $m->getBody());
            $this->assertSame(AMQPMessage::DELIVERY_MODE_PERSISTENT, $m->get('delivery_mode'));
            $this->assertSame('application/json', $m->get('content_type'));
        });
        $broker = new RabbitMqBroker(fn () => $channel);
        $broker->publish('orders.placed', '{"a":1}');
        $broker->publish('orders.placed', '{"a":1}'); // one connection, one exchange declaration
    }

    public function testDeclareCreatesADurableQueuePerGroupAndTopicBoundToTheExchange(): void
    {
        $channel = $this->createMock(AMQPChannel::class);
        $channel->expects($this->once())->method('queue_declare')->with('billing.orders.*', false, true, false, false, false, []);
        $channel->expects($this->once())->method('queue_bind')->with('billing.orders.*', 'naluz.events', 'orders.*');
        $broker = new RabbitMqBroker(fn () => $channel);
        $broker->declare(['orders.*'], 'billing');
        $broker->declare(['orders.*'], 'billing');
    }

    public function testReceiveReturnsADeliveryWhoseAckAcknowledgesTheTag(): void
    {
        $message = new AMQPMessage('{"x":1}');
        $message->setDeliveryInfo(42, false, 'naluz.events', 'orders.placed');
        $channel = $this->createMock(AMQPChannel::class);
        $channel->method('basic_get')->with('billing.orders.placed', false)->willReturn($message);
        $channel->expects($this->once())->method('basic_ack')->with(42);
        $d = (new RabbitMqBroker(fn () => $channel))->receive(['orders.placed'], 'billing', 100);
        $this->assertSame('{"x":1}', $d?->body);
        $this->assertSame('orders.placed', $d->topic);
        $this->assertSame('42', $d->id);
        $d->ack();
        $d->ack();
    }

    public function testReceiveReturnsNullAfterTheTimeoutWhenTheQueueIsEmpty(): void
    {
        $channel = $this->createMock(AMQPChannel::class);
        $channel->method('basic_get')->willReturn(null);
        $started = microtime(true);
        $this->assertNull((new RabbitMqBroker(fn () => $channel))->receive(['t'], 'g', 120));
        $this->assertGreaterThanOrEqual(0.1, microtime(true) - $started);
    }

    public function testFailureBecomesMessagingExceptionAndTheNextCallReconnects(): void
    {
        $opened = 0;
        $broken = $this->createMock(AMQPChannel::class);
        $broken->method('basic_publish')->willThrowException(new AMQPConnectionClosedException('gone'));
        $healthy = $this->createMock(AMQPChannel::class);
        $healthy->expects($this->once())->method('basic_publish');
        $broker = new RabbitMqBroker(function () use (&$opened, $broken, $healthy) {
            return $opened++ === 0 ? $broken : $healthy;
        });
        try {
            $broker->publish('t', 'x');
            $this->fail('expected a MessagingException');
        } catch (MessagingException) {
            $this->addToAssertionCount(1);
        }
        $broker->publish('t', 'x');
        $this->assertSame(2, $opened);
    }
}
