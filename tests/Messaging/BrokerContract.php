<?php

declare(strict_types=1);

namespace Naluz\Tests\Messaging;

use Naluz\Messaging\Broker;
use PHPUnit\Framework\TestCase;

/** Behaviour every Broker driver must share. */
abstract class BrokerContract extends TestCase
{
    abstract protected function broker(): Broker;

    public function testPublishReceiveAck(): void
    {
        $b = $this->broker();
        $b->declare(['t.a'], 'g1');
        $b->publish('t.a', 'one');
        $d = $b->receive(['t.a'], 'g1', 200);
        $this->assertNotNull($d);
        $this->assertSame('one', $d->body);
        $this->assertSame('t.a', $d->topic);
        $d->ack();
        $this->assertNull($b->receive(['t.a'], 'g1', 100));
    }

    public function testMessagesKeepOrderWithinATopic(): void
    {
        $b = $this->broker();
        $b->declare(['t.a'], 'g1');
        foreach (['1', '2', '3'] as $n) {
            $b->publish('t.a', $n);
        }
        $seen = [];
        while ($d = $b->receive(['t.a'], 'g1', 100)) {
            $seen[] = $d->body;
            $d->ack();
        }
        $this->assertSame(['1', '2', '3'], $seen);
    }

    public function testEveryGroupGetsItsOwnCopy(): void
    {
        $b = $this->broker();
        $b->declare(['t.a'], 'billing');
        $b->declare(['t.a'], 'email');
        $b->publish('t.a', 'x');
        $one = $b->receive(['t.a'], 'billing', 200);
        $two = $b->receive(['t.a'], 'email', 200);
        $this->assertSame('x', $one?->body);
        $this->assertSame('x', $two?->body);
        $one->ack();
        $two->ack();
    }

    public function testMembersOfOneGroupShareTheWork(): void
    {
        $b = $this->broker();
        $b->declare(['t.a'], 'g');
        $b->publish('t.a', 'only-once');
        $first = $b->receive(['t.a'], 'g', 200);
        $this->assertNotNull($first);
        $this->assertNull($b->receive(['t.a'], 'g', 100), 'already delivered to a member of the group');
        $first->ack();
    }

    public function testReceivesFromSeveralTopics(): void
    {
        $b = $this->broker();
        $b->declare(['t.a', 't.b'], 'g');
        $b->publish('t.b', 'from-b');
        $d = $b->receive(['t.a', 't.b'], 'g', 200);
        $this->assertSame('t.b', $d?->topic);
        $this->assertSame('from-b', $d->body);
        $d->ack();
    }

    public function testAckTwiceIsHarmless(): void
    {
        $b = $this->broker();
        $b->declare(['t.a'], 'g');
        $b->publish('t.a', 'x');
        $d = $b->receive(['t.a'], 'g', 200);
        $d->ack();
        $d->ack();
        $this->assertNull($b->receive(['t.a'], 'g', 100));
    }
}
