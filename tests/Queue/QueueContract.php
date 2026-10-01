<?php

declare(strict_types=1);

namespace Naluz\Tests\Queue;

use Naluz\Console\Kernel;
use Naluz\Console\Output;
use Naluz\Queue\DatabaseQueue;
use Naluz\Queue\FailedJobs;
use Naluz\Queue\InvalidPayloadException;
use Naluz\Queue\Job;
use Naluz\Queue\Payload;
use Naluz\Queue\Queue;
use Naluz\Queue\QueueManager;
use Naluz\Queue\RedisQueue;
use Naluz\Queue\Worker;
use Naluz\Security\Encrypter;
use Naluz\Tests\Support\RedisServer;
use Naluz\Tests\TestCase;

final class Recorder
{
    /** @var list<string> */
    public static array $log = [];
}

final class EchoJob extends Job
{
    public function __construct(public readonly string $message = '', public readonly int $n = 0)
    {
    }

    public function handle(Recorder $r = new Recorder()): void
    {
        Recorder::$log[] = "{$this->message}:{$this->n}";
    }
}

final class FlakyJob extends Job
{
    protected int $tries = 3;
    protected int|array $backoff = [0];
    public static int $failUntil = 2;
    public static int $calls = 0;
    public static ?string $failedHook = null;

    public function __construct(public readonly string $tag = 'x')
    {
    }

    public function handle(): void
    {
        if (++self::$calls <= self::$failUntil) {
            throw new \RuntimeException('transient failure #' . self::$calls);
        }
        Recorder::$log[] = 'flaky-ok';
    }

    public function failed(\Throwable $e): void
    {
        self::$failedHook = $e->getMessage();
    }
}

abstract class QueueContract extends TestCase
{
    abstract protected function queue(): Queue;

    protected function setUp(): void
    {
        parent::setUp();
        Recorder::$log = [];
        FlakyJob::$calls = 0;
        FlakyJob::$failUntil = 2;
        FlakyJob::$failedHook = null;
        $this->app->make(QueueManager::class)->extend('database', $this->queue());
        $this->app->make(\Naluz\Config\Repository::class)->set('queue.default', 'database');
    }

    protected function worker(): Worker
    {
        return $this->app->make(Worker::class);
    }

    public function testDispatchAndProcess(): void
    {
        EchoJob::dispatch('hello', 7);
        $this->assertSame(1, $this->queue()->size());
        $this->assertSame('processed', $this->worker()->runNextJob());
        $this->assertSame(['hello:7'], Recorder::$log);
        $this->assertSame(0, $this->queue()->size());
        $this->assertNull($this->worker()->runNextJob());
    }

    public function testFifoAndNamedQueues(): void
    {
        EchoJob::dispatch('a', 1);
        (new EchoJob('b', 2))->onQueue('mail');
        $this->app->make(QueueManager::class)->dispatch((new EchoJob('b', 2))->onQueue('mail'));
        EchoJob::dispatch('c', 3);
        $this->worker()->daemon('default', 1, 0, true);
        $this->assertSame(['a:1', 'c:3'], Recorder::$log);
        $this->worker()->daemon('mail', 1, 0, true);
        $this->assertSame(['a:1', 'c:3', 'b:2'], Recorder::$log);
    }

    public function testDelayedJobsWaitUntilDue(): void
    {
        $this->app->make(QueueManager::class)->dispatch((new EchoJob('later'))->delay(3600));
        $this->assertNull($this->worker()->runNextJob(), 'not available yet');
        $this->assertSame(1, $this->queue()->size());
    }

    public function testRetryThenSucceed(): void
    {
        FlakyJob::dispatch();
        $this->assertSame('released', $this->worker()->runNextJob());
        $this->assertSame('released', $this->worker()->runNextJob());
        $this->assertSame('processed', $this->worker()->runNextJob());
        $this->assertSame(['flaky-ok'], Recorder::$log);
        $this->assertSame(3, FlakyJob::$calls);
        $this->assertCount(0, $this->app->make(FailedJobs::class)->all());
    }

    public function testExhaustedJobsMoveToFailedAndCanBeRetried(): void
    {
        FlakyJob::$failUntil = 99;
        FlakyJob::dispatch();
        $this->assertSame(['released', 'released', 'failed'], [
            $this->worker()->runNextJob(), $this->worker()->runNextJob(), $this->worker()->runNextJob(),
        ]);
        $this->assertSame(3, FlakyJob::$calls);
        $this->assertSame('transient failure #3', FlakyJob::$failedHook, 'failed() hook called once');
        $this->assertSame(0, $this->queue()->size());

        $failed = $this->app->make(FailedJobs::class)->all();
        $this->assertCount(1, $failed);
        $this->assertStringContainsString('transient failure #3', $failed[0]['exception']);
        $this->assertStringNotContainsString('transient', $failed[0]['payload'], 'payload is encrypted at rest');

        // retry via CLI once the underlying problem is fixed
        FlakyJob::$failUntil = 0;
        $stream = fopen('php://memory', 'w+');
        $kernel = new Kernel($this->app, new Output($stream));
        $this->assertSame(0, $kernel->run(['naluz', 'queue:retry', (string) $failed[0]['id']]));
        $this->assertCount(0, $this->app->make(FailedJobs::class)->all());
        $this->assertSame('processed', $this->worker()->runNextJob());
        $this->assertSame(['flaky-ok'], Recorder::$log);
    }

    public function testTamperedAndForeignPayloadsAreRejectedNotExecuted(): void
    {
        $queue = $this->queue();
        $queue->pushRaw('this-is-not-a-valid-payload');
        $other = (new Payload(new Encrypter(Encrypter::generateKey())))->encode(new EchoJob('forged'));
        $queue->pushRaw($other); // valid format, but encrypted with a different key
        $this->assertSame('failed', $this->worker()->runNextJob());
        $this->assertSame('failed', $this->worker()->runNextJob());
        $this->assertSame([], Recorder::$log);
        $this->assertCount(2, $this->app->make(FailedJobs::class)->all());
    }

    public function testPayloadsMustDescribeAJobSubclass(): void
    {
        $enc = $this->app->make(Encrypter::class);
        $payloads = new Payload($enc);
        $evil = $enc->encrypt(['class' => \ArrayObject::class, 'data' => []]);
        $this->expectException(InvalidPayloadException::class);
        $payloads->decode($evil);
    }

    public function testDaemonStopsAfterMaxJobs(): void
    {
        foreach (range(1, 5) as $i) {
            EchoJob::dispatch('j', $i);
        }
        $this->assertSame(3, $this->worker()->daemon('default', 1, 3));
        $this->assertSame(2, $this->queue()->size());
    }

    public function testPayloadRoundTripKeepsTypes(): void
    {
        $payloads = $this->app->make(Payload::class);
        $job = $payloads->decode($payloads->encode(new EchoJob('héllo "q"', 42)));
        $this->assertInstanceOf(EchoJob::class, $job);
        $this->assertSame('héllo "q"', $job->message);
        $this->assertSame(42, $job->n);
    }
}
