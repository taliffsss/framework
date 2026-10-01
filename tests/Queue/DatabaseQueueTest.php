<?php

declare(strict_types=1);

namespace Naluz\Tests\Queue;

use Naluz\Console\Kernel;
use Naluz\Console\Output;
use Naluz\Queue\DatabaseQueue;
use Naluz\Queue\Payload;
use Naluz\Queue\Queue;
use Naluz\Queue\QueueManager;
use Naluz\Queue\RedisQueue;
use Naluz\Tests\Support\RedisServer;

final class DatabaseQueueTest extends QueueContract
{
    protected function queue(): Queue
    {
        return new DatabaseQueue($this->db(), $this->app->make(Payload::class), 90);
    }

    public function testTwoWorkersCannotClaimTheSameJob(): void
    {
        EchoJob::dispatch('once');
        $q = $this->queue();
        $first = $q->pop();
        $second = $q->pop();
        $this->assertNotNull($first);
        $this->assertNull($second, 'reserved jobs are invisible to other workers');
        $this->assertSame(1, $first->attempts);
    }

    public function testCrashedWorkersJobBecomesAvailableAgainAfterRetryAfter(): void
    {
        EchoJob::dispatch('crash');
        $q = new DatabaseQueue($this->db(), $this->app->make(Payload::class), 90);
        $q->pop(); // worker "crashes" without deleting
        $this->assertNull($q->pop());
        $this->db()->table('jobs')->update(['reserved_at' => time() - 91]);
        $again = $q->pop();
        $this->assertNotNull($again);
        $this->assertSame(2, $again->attempts);
    }

    public function testSyncDriverRunsInline(): void
    {
        $this->app->make(\Naluz\Config\Repository::class)->set('queue.default', 'sync');
        EchoJob::dispatch('inline', 1);
        $this->assertSame(['inline:1'], Recorder::$log);
    }

    public function testSyncDriverPropagatesExceptions(): void
    {
        $this->app->make(\Naluz\Config\Repository::class)->set('queue.default', 'sync');
        FlakyJob::$failUntil = 99;
        $this->expectException(\RuntimeException::class);
        FlakyJob::dispatch();
    }

    public function testQueueWorkCommand(): void
    {
        EchoJob::dispatch('cli', 1);
        $stream = fopen('php://memory', 'w+');
        $code = (new Kernel($this->app, new Output($stream)))->run(['naluz', 'queue:work', '--stop-when-empty']);
        rewind($stream);
        $this->assertSame(0, $code);
        $this->assertStringContainsString('Handled 1 job', (string) stream_get_contents($stream));
        $this->assertSame(['cli:1'], Recorder::$log);
    }
}
