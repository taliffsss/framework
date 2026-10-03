<?php

declare(strict_types=1);

namespace Naluz\Tests\Messaging;

use Naluz\Messaging\Brokers\KafkaBroker;
use Naluz\Messaging\MessagingException;
use PHPUnit\Framework\TestCase;

/** Exercises the driver against stand-ins shaped like RdKafka\Producer / KafkaConsumer (ext-rdkafka is not needed). */
final class KafkaBrokerTest extends TestCase
{
    private function producer(array &$sent, int $flushResult = 0): object
    {
        return new class ($sent, $flushResult) {
            public function __construct(private array &$sent, private int $flushResult)
            {
            }

            public function newTopic(string $name): object
            {
                $sent = &$this->sent;
                return new class ($name, $sent) {
                    public function __construct(private string $name, private array &$sent)
                    {
                    }

                    public function produce(int $partition, int $flags, ?string $payload = null, ?string $key = null): void
                    {
                        $this->sent[] = [$this->name, $payload, $key, $partition];
                    }
                };
            }

            public function poll(int $ms): int
            {
                return 0;
            }

            public function flush(int $ms): int
            {
                return $this->flushResult;
            }
        };
    }

    /** @param list<object> $messages */
    private function consumer(array $messages, array &$log): object
    {
        return new class ($messages, $log) {
            /** @param list<object> $messages */
            public function __construct(private array $messages, private array &$log)
            {
            }

            public function subscribe(array $topics): void
            {
                $this->log[] = ['subscribe', $topics];
            }

            public function consume(int $ms): object
            {
                return array_shift($this->messages) ?? self::msg(-185);
            }

            public function commit(object $m): void
            {
                $this->log[] = ['commit', $m->offset];
            }

            public static function msg(int $err, string $topic = 't', string $payload = '', int $offset = 0): object
            {
                return new class ($err, $topic, $payload, $offset) {
                    public int $partition = 0;

                    public function __construct(public int $err, public string $topic_name, public string $payload, public int $offset)
                    {
                    }

                    public function errstr(): string
                    {
                        return 'broker says no';
                    }
                };
            }
        };
    }

    public function testPublishUsesTheKeyAndWaitsForTheBrokerToConfirm(): void
    {
        $sent = [];
        $settings = [];
        $broker = new KafkaBroker(['metadata.broker.list' => 'k:9092'], 'earliest', 1000, function (array $s) use (&$sent, &$settings) {
            $settings = $s;
            return $this->producer($sent);
        });
        $broker->publish('orders', '{"a":1}', 'order-7');
        $this->assertSame([['orders', '{"a":1}', 'order-7', -1]], $sent);
        $this->assertSame('true', $settings['enable.idempotence']);
        $this->assertSame('all', $settings['acks']);
        $this->assertSame('k:9092', $settings['metadata.broker.list']);
    }

    public function testUnconfirmedPublishThrows(): void
    {
        $sent = [];
        $broker = new KafkaBroker([], 'earliest', 10, fn () => $this->producer($sent, -185));
        $this->expectException(MessagingException::class);
        $broker->publish('t', 'x');
    }

    public function testConsumerGroupSettingsAndCommitOnlyAfterAck(): void
    {
        $log = [];
        $settings = [];
        $msg = $this->consumer([], $log)::msg(0, 'orders', '{"a":1}', 41);
        $broker = new KafkaBroker(['metadata.broker.list' => 'k:9092'], 'latest', 1000, null, function (array $s) use (&$settings, &$log, $msg) {
            $settings = $s;
            return $this->consumer([$msg], $log);
        });
        $d = $broker->receive(['orders'], 'billing', 100);
        $this->assertSame('billing', $settings['group.id']);
        $this->assertSame('false', $settings['enable.auto.commit']);
        $this->assertSame('latest', $settings['auto.offset.reset']);
        $this->assertSame('{"a":1}', $d?->body);
        $this->assertSame('orders', $d->topic);
        $this->assertSame([['subscribe', ['orders']]], $log, 'nothing committed before ack');
        $d->ack();
        $this->assertSame(['commit', 41], $log[1]);
        $this->assertNull($broker->receive(['orders'], 'billing', 100), 'timed out');
        $this->assertCount(2, $log, 'subscribed once, not on every poll');
    }

    public function testBrokerErrorsThrowButEofAndTimeoutDoNot(): void
    {
        $log = [];
        $broker = new KafkaBroker([], 'earliest', 1000, null, function () use (&$log) {
            return $this->consumer([$this->consumer([], $log)::msg(-191), $this->consumer([], $log)::msg(-185), $this->consumer([], $log)::msg(-195)], $log);
        });
        $this->assertNull($broker->receive(['t'], 'g', 10));
        $this->assertNull($broker->receive(['t'], 'g', 10));
        $this->expectException(MessagingException::class);
        $this->expectExceptionMessage('broker says no');
        $broker->receive(['t'], 'g', 10);
    }

    public function testMissingExtensionGivesAHelpfulError(): void
    {
        if (extension_loaded('rdkafka')) {
            $this->markTestSkipped('ext-rdkafka is installed.');
        }
        $this->expectException(MessagingException::class);
        $this->expectExceptionMessage('ext-rdkafka');
        (new KafkaBroker([]))->publish('t', 'x');
    }
}
