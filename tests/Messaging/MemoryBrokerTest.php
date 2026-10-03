<?php

declare(strict_types=1);

namespace Naluz\Tests\Messaging;

use Naluz\Messaging\Broker;
use Naluz\Messaging\Brokers\MemoryBroker;

final class MemoryBrokerTest extends BrokerContract
{
    protected function broker(): Broker
    {
        return new MemoryBroker();
    }

    public function testUnackedMessageIsDeliveredAgain(): void
    {
        $b = new MemoryBroker();
        $b->declare(['t'], 'g');
        $b->publish('t', 'x');
        $this->assertSame('x', $b->receive(['t'], 'g')?->body);
        $this->assertNull($b->receive(['t'], 'g'), 'in flight, not redelivered yet');
        $b->requeueUnacked('g'); // the consumer crashed
        $this->assertSame('x', $b->receive(['t'], 'g')?->body, 'not acked, so it comes back');
    }

    public function testAGroupOnlySeesEventsFromWhenItWasDeclared(): void
    {
        $b = new MemoryBroker();
        $b->publish('t', 'before');
        $b->declare(['t'], 'late');
        $this->assertNull($b->receive(['t'], 'late'));
        $b->publish('t', 'after');
        $this->assertSame('after', $b->receive(['t'], 'late')?->body);
    }
}
