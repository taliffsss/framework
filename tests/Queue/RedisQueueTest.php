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

final class RedisQueueTest extends QueueContract
{
    use RedisServer;

    protected function queue(): Queue
    {
        static $cache = [];
        return $cache[spl_object_id($this->app)] ??= new RedisQueue($this->redis(), $this->app->make(Payload::class), 90);
    }

    public function testCrashedWorkersJobIsRedeliveredWithAttemptsIncremented(): void
    {
        $q = new RedisQueue($this->redis(), $this->app->make(Payload::class), 1);
        $this->app->make(QueueManager::class)->extend('database', $q);
        EchoJob::dispatch('crash');
        $first = $q->pop();
        $this->assertSame(1, $first->attempts);
        $this->assertNull($q->pop());
        sleep(2); // visibility timeout (1s) elapses
        $second = $q->pop();
        $this->assertNotNull($second);
        $this->assertSame(2, $second->attempts);
    }
}
