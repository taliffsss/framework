<?php

declare(strict_types=1);

namespace Naluz\Tests\Messaging;

use Naluz\Config\Repository;
use Naluz\Console\Kernel;
use Naluz\Console\Output;
use Naluz\Log\LogManager;
use Naluz\Messaging\BrokerManager;
use Naluz\Messaging\Brokers\MemoryBroker;
use Naluz\Messaging\Consumer;
use Naluz\Messaging\EventBus;
use Naluz\Messaging\Message;
use Naluz\Messaging\PublishableEvent;
use Naluz\Messaging\Subscriber;
use Naluz\Tests\TestCase;

final class Recorder
{
    /** @var list<string> */
    public static array $log = [];
    public static int $failures = 0;
}

final class RecordingSubscriber implements Subscriber
{
    public function handle(Message $message): void
    {
        Recorder::$log[] = $message->topic . ':' . json_encode($message->payload) . ':a' . $message->attempts();
    }
}

final class FailingSubscriber implements Subscriber
{
    public function handle(Message $message): void
    {
        if (Recorder::$failures-- > 0) {
            throw new \RuntimeException('boom');
        }
        Recorder::$log[] = 'recovered:a' . $message->attempts();
    }
}

final class NotASubscriber
{
}

final class OrderPlaced implements PublishableEvent
{
    public function __construct(private readonly int $id)
    {
    }

    public function topic(): string
    {
        return 'orders.placed';
    }

    public function payload(): array
    {
        return ['id' => $this->id];
    }

    public function key(): ?string
    {
        return 'order-' . $this->id;
    }
}

final class EventBusTest extends TestCase
{
    private MemoryBroker $broker;

    protected function setUp(): void
    {
        parent::setUp();
        Recorder::$log = [];
        Recorder::$failures = 0;
        $this->broker = new MemoryBroker();
        $this->app->make(BrokerManager::class)->extend('memory', $this->broker);
        $this->configure(['orders.placed' => [RecordingSubscriber::class]]);
    }

    /** @param array<string,list<class-string>> $subscribers */
    private function configure(array $subscribers, string $signingKey = ''): void
    {
        $config = $this->app->make(Repository::class);
        $config->set('messaging.subscribers', $subscribers);
        $config->set('messaging.signing_key', $signingKey);
        // singletons built from config are rebuilt for the new settings
        $this->app->singleton(\Naluz\Messaging\Codec::class, fn () => new \Naluz\Messaging\Codec($signingKey));
        $this->app->singleton(EventBus::class, fn ($c) => new EventBus($c->make(BrokerManager::class), $c->make(\Naluz\Messaging\Codec::class), $config));
        $this->app->singleton(Consumer::class, fn ($c) => new Consumer($c, $c->make(BrokerManager::class), $c->make(EventBus::class), $c->make(\Naluz\Messaging\Codec::class), $config, $c->make(\Psr\Log\LoggerInterface::class)));
    }

    private function consumer(): Consumer
    {
        return $this->app->make(Consumer::class);
    }

    private function bus(): EventBus
    {
        return $this->app->make(EventBus::class);
    }

    public function testPublishedEventReachesTheSubscriber(): void
    {
        $this->broker->declare(['orders.placed'], 'billing');
        $this->bus()->publish('orders.placed', ['id' => 7]);
        $this->assertSame('processed', $this->consumer()->consumeOne(['orders.placed'], 'billing', null, 50));
        $this->assertSame(['orders.placed:{"id":7}:a1'], Recorder::$log);
        $this->assertNull($this->consumer()->consumeOne(['orders.placed'], 'billing', null, 50));
    }

    public function testPublishableEventsCarryTheirTopicKeyAndType(): void
    {
        $message = $this->bus()->dispatch(new OrderPlaced(9));
        $this->assertSame(OrderPlaced::class, $message->type);
        $this->assertSame('orders.placed', $message->topic);
        $this->assertSame('order-9', $this->broker->key('orders.placed', 0));
    }

    public function testTopicNamesAreValidated(): void
    {
        foreach (['', 'a b', "a\nb", '../x', 'a*', str_repeat('x', 201)] as $bad) {
            try {
                $this->bus()->publish($bad, []);
                $this->fail('accepted topic: ' . $bad);
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testFailedMessageIsRetriedForThatGroupOnlyThenSucceeds(): void
    {
        Recorder::$failures = 1;
        $this->configure(['orders.placed' => [FailingSubscriber::class]]);
        $this->broker->declare(['orders.placed'], 'billing');
        $this->broker->declare(['orders.placed'], 'email');
        $this->bus()->publish('orders.placed', ['id' => 1]);

        $this->assertSame('retried', $this->consumer()->consumeOne(['orders.placed'], 'billing', null, 50, 3));
        $this->assertSame('processed', $this->consumer()->consumeOne(['orders.placed'], 'billing', null, 50, 3));
        $this->assertSame(['recovered:a2'], Recorder::$log, 'second attempt, header carried over');

        // the email group got the original once, plus a retry addressed to billing which it must skip
        $this->assertSame('processed', $this->consumer()->consumeOne(['orders.placed'], 'email', null, 50, 3));
        $this->assertSame('skipped', $this->consumer()->consumeOne(['orders.placed'], 'email', null, 50, 3));
        $this->assertNull($this->consumer()->consumeOne(['orders.placed'], 'email', null, 50, 3));
    }

    public function testExhaustedRetriesGoToTheDeadLetterTopic(): void
    {
        Recorder::$failures = 99;
        $this->configure(['orders.placed' => [FailingSubscriber::class]]);
        $this->broker->declare(['orders.placed'], 'billing');
        $this->broker->declare(['orders.placed.dlq'], 'ops');
        $this->bus()->publish('orders.placed', ['id' => 1]);
        $results = [];
        while (($r = $this->consumer()->consumeOne(['orders.placed'], 'billing', null, 50, 3)) !== null) {
            $results[] = $r;
        }
        $this->assertSame(['retried', 'retried', 'dead'], $results);

        $dlq = $this->broker->receive(['orders.placed.dlq'], 'ops', 50);
        $this->assertNotNull($dlq);
        $dead = (new \Naluz\Messaging\Codec())->decode($dlq->body, $dlq->topic);
        $this->assertSame(['id' => 1], $dead->payload);
        $this->assertSame('orders.placed', $dead->headers['x-original-topic']);
        $this->assertSame('billing', $dead->headers['x-failed-group']);
        $this->assertStringContainsString('boom', $dead->headers['x-error']);
        $this->assertSame('3', $dead->headers['x-attempts']);

        // and the dead letter can be consumed by anyone, whatever group it last failed in
        $this->configure(['orders.placed.dlq' => [RecordingSubscriber::class]]);
        $this->assertSame('processed', $this->consumer()->handle($dlq, 'ops'));
    }

    public function testNoSubscriberAcknowledgesWithoutHandling(): void
    {
        $this->configure([]);
        $this->broker->declare(['orders.placed'], 'billing');
        $this->bus()->publish('orders.placed', []);
        $this->assertSame('skipped', $this->consumer()->consumeOne(['orders.placed'], 'billing', null, 50));
        $this->assertNull($this->consumer()->consumeOne(['orders.placed'], 'billing', null, 50));
    }

    public function testWildcardSubscriptionsMatchTopics(): void
    {
        $this->configure(['orders.*' => [RecordingSubscriber::class]]);
        $this->broker->declare(['orders.shipped'], 'g');
        $this->bus()->publish('orders.shipped', ['id' => 2]);
        $this->assertSame('processed', $this->consumer()->consumeOne(['orders.shipped'], 'g', null, 50));
        $this->assertSame(['orders.shipped:{"id":2}:a1'], Recorder::$log);
    }

    public function testASubscriberThatIsNotASubscriberFailsTheMessageInsteadOfRunningIt(): void
    {
        $this->configure(['orders.placed' => [NotASubscriber::class]]);
        $this->broker->declare(['orders.placed'], 'g');
        $this->bus()->publish('orders.placed', []);
        $this->assertSame('retried', $this->consumer()->consumeOne(['orders.placed'], 'g', null, 50, 3));
    }

    public function testMessageTypeFromTheWireIsNeverInstantiated(): void
    {
        $this->broker->declare(['orders.placed'], 'g');
        $this->broker->publish('orders.placed', json_encode(['type' => 'Naluz\\Messaging\\Nope', 'payload' => ['a' => 1]]));
        $this->assertSame('processed', $this->consumer()->consumeOne(['orders.placed'], 'g', null, 50));
    }

    public function testSigningRejectsForgedAndUnsignedMessagesAndDropsThem(): void
    {
        $this->configure(['orders.placed' => [RecordingSubscriber::class]], 'k3y');
        $this->broker->declare(['orders.placed'], 'g');
        $this->bus()->publish('orders.placed', ['id' => 1]);                                    // signed by us
        $this->broker->publish('orders.placed', (new \Naluz\Messaging\Codec())->encode(new Message('f', 'orders.placed', 'x', ['id' => 666]))); // forged, unsigned
        $this->broker->publish('orders.placed', 'garbage');

        $this->assertSame('processed', $this->consumer()->consumeOne(['orders.placed'], 'g', null, 50));
        $this->assertSame('invalid', $this->consumer()->consumeOne(['orders.placed'], 'g', null, 50));
        $this->assertSame('invalid', $this->consumer()->consumeOne(['orders.placed'], 'g', null, 50));
        $this->assertNull($this->consumer()->consumeOne(['orders.placed'], 'g', null, 50));
        $this->assertSame(['orders.placed:{"id":1}:a1'], Recorder::$log);
    }

    public function testDaemonStopsWhenEmptyAndHonoursMaxMessages(): void
    {
        $this->broker->declare(['orders.placed'], 'g');
        foreach ([1, 2, 3] as $i) {
            $this->bus()->publish('orders.placed', ['id' => $i]);
        }
        $this->assertSame(2, $this->consumer()->daemon(['orders.placed'], 'g', null, 3, 0, 2, true, 20));
        $this->assertSame(1, $this->consumer()->daemon(['orders.placed'], 'g', null, 3, 0, 0, true, 20));
    }

    public function testUnknownDriverIsRejected(): void
    {
        $this->app->make(Repository::class)->set('messaging.connections.weird', ['driver' => 'carrier-pigeon']);
        $this->expectException(\InvalidArgumentException::class);
        $this->app->make(BrokerManager::class)->connection('weird');
    }

    public function testRabbitAndKafkaConnectionsExplainMissingDependencies(): void
    {
        $config = $this->app->make(Repository::class);
        $config->set('messaging.connections.kafka', ['driver' => 'kafka']);
        $manager = $this->app->make(BrokerManager::class);
        $broker = $manager->connection('kafka'); // lazy: building it needs nothing
        if (!extension_loaded('rdkafka')) {
            $this->expectException(\Naluz\Messaging\MessagingException::class);
            $broker->publish('t', 'x');
        } else {
            $this->addToAssertionCount(1);
        }
    }

    public function testConsoleCommands(): void
    {
        $run = function (array $argv): array {
            $stream = fopen('php://memory', 'w+');
            $code = (new Kernel($this->app, new Output($stream)))->run($argv);
            rewind($stream);
            return [$code, stream_get_contents($stream)];
        };
        [$code, $out] = $run(['naluz', 'messaging:declare', 'orders.placed', '--group=billing']);
        $this->assertSame(0, $code);
        $this->assertStringContainsString('billing', $out);

        [$code, $out] = $run(['naluz', 'messaging:publish', 'orders.placed', '{"id":5}']);
        $this->assertSame(0, $code, $out);
        [$code] = $run(['naluz', 'messaging:publish', 'orders.placed', 'nope']);
        $this->assertSame(1, $code);
        [$code, $out] = $run(['naluz', 'messaging:publish', 'bad topic', '{}']);
        $this->assertSame(1, $code, 'validation errors are reported, not thrown');

        [$code, $out] = $run(['naluz', 'messaging:consume', 'orders.placed', '--group=billing', '--stop-when-empty']);
        $this->assertSame(0, $code);
        $this->assertStringContainsString('Handled 1 message', $out);
        $this->assertSame(['orders.placed:{"id":5}:a1'], Recorder::$log);

        [$code] = $run(['naluz', 'messaging:consume']);
        $this->assertSame(1, $code);
    }
}
