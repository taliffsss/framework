<?php

declare(strict_types=1);

namespace Naluz\Tests\Unit;

use Naluz\Container\Container;
use Naluz\Container\ContainerException;
use Naluz\Container\NotFoundException;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

interface Greeter
{
    public function greet(): string;
}

final class English implements Greeter
{
    public function greet(): string
    {
        return 'hello';
    }
}

final class Consumer
{
    public function __construct(public readonly Greeter $greeter, public readonly int $times = 2)
    {
    }

    public function say(Greeter $g, string $suffix): string
    {
        return str_repeat($g->greet(), $this->times) . $suffix;
    }
}

final class CycleA
{
    public function __construct(CycleB $b)
    {
    }
}

final class CycleB
{
    public function __construct(CycleA $a)
    {
    }
}

final class ContainerTest extends TestCase
{
    public function testIsPsr11(): void
    {
        $this->assertInstanceOf(ContainerInterface::class, new Container());
    }

    public function testAutowiringAndBindings(): void
    {
        $c = new Container();
        $c->bind(Greeter::class, English::class);
        $consumer = $c->make(Consumer::class);
        $this->assertSame('hello', $consumer->greeter->greet());
        $this->assertSame(2, $consumer->times);
        $this->assertSame(5, $c->make(Consumer::class, ['times' => 5])->times);
    }

    public function testSingletonAndInstance(): void
    {
        $c = new Container();
        $c->singleton(Greeter::class, English::class);
        $this->assertSame($c->get(Greeter::class), $c->get(Greeter::class));
        $obj = new \stdClass();
        $c->instance('thing', $obj);
        $this->assertSame($obj, $c->get('thing'));
        $this->assertTrue($c->has('thing'));
    }

    public function testCallInjectsAndCoercesRouteParameters(): void
    {
        $c = new Container();
        $c->bind(Greeter::class, English::class);
        $this->assertSame('hellohello!', $c->call([Consumer::class, 'say'], ['suffix' => '!']));
        $this->assertSame(10, $c->call(fn (int $id) => $id * 2, ['id' => '5']));
    }

    public function testMissingAndCircular(): void
    {
        $c = new Container();
        $this->expectException(NotFoundException::class);
        $c->get('Nope\\Missing');
    }

    public function testCircularDependencyDetected(): void
    {
        $this->expectException(ContainerException::class);
        (new Container())->make(CycleA::class);
    }

    public function testUnresolvableInterface(): void
    {
        $this->expectException(ContainerException::class);
        (new Container())->make(Consumer::class);
    }
}
