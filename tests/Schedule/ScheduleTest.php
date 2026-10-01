<?php

declare(strict_types=1);

namespace Naluz\Tests\Schedule;

use Naluz\Cache\ArrayCache;
use Naluz\Console\Kernel;
use Naluz\Console\Output;
use Naluz\Container\Container;
use Naluz\Schedule\CronExpression;
use Naluz\Schedule\Schedule;
use Naluz\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\NullLogger;

final class ScheduleTest extends TestCase
{
    private function at(string $when): \DateTimeImmutable
    {
        return new \DateTimeImmutable($when, new \DateTimeZone('UTC'));
    }

    #[DataProvider('cronCases')]
    public function testCronMatching(string $expr, string $when, bool $due): void
    {
        $this->assertSame($due, (new CronExpression($expr))->isDue($this->at($when)), "{$expr} @ {$when}");
    }

    public static function cronCases(): array
    {
        return [
            ['* * * * *', '2026-03-04 05:06', true],
            ['*/5 * * * *', '2026-03-04 05:10', true],
            ['*/5 * * * *', '2026-03-04 05:11', false],
            ['0 9 * * 1-5', '2026-03-04 09:00', true],   // Wednesday
            ['0 9 * * 1-5', '2026-03-07 09:00', false],  // Saturday
            ['30 2 1,15 * *', '2026-03-15 02:30', true],
            ['30 2 1,15 * *', '2026-03-16 02:30', false],
            ['0 0 1 jan *', '2026-01-01 00:00', true],
            ['0 0 * * mon', '2026-03-02 00:00', true],
            ['0 0 * * 7', '2026-03-01 00:00', true],      // 7 = Sunday
            ['10-30/10 * * * *', '2026-03-04 05:20', true],
            ['10-30/10 * * * *', '2026-03-04 05:25', false],
            ['@hourly', '2026-03-04 05:00', true],
            ['@daily', '2026-03-04 00:00', true],
            ['@daily', '2026-03-04 00:01', false],
            // standard cron: restricted day-of-month AND day-of-week => either matches
            ['0 0 13 * 5', '2026-03-13 00:00', true],     // Friday the 13th
            ['0 0 13 * 5', '2026-03-06 00:00', true],     // a Friday that isn't the 13th
            ['0 0 13 * 5', '2026-03-10 00:00', false],
        ];
    }

    #[DataProvider('badExpressions')]
    public function testInvalidExpressionsAreRejected(string $expr): void
    {
        $this->assertFalse(CronExpression::isValid($expr));
    }

    public static function badExpressions(): array
    {
        return [[''], ['* * * *'], ['* * * * * *'], ['60 * * * *'], ['* 24 * * *'], ['* * 0 * *'], ['* * * 13 *'], ['*/0 * * * *'], ['5-1 * * * *'], ['a * * * *'], ["* * * * *; rm -rf /"]];
    }

    public function testNextRunDate(): void
    {
        $next = fn (string $e, string $from) => (new CronExpression($e))->nextRunDate($this->at($from))->format('Y-m-d H:i');
        $this->assertSame('2026-03-04 05:07', $next('* * * * *', '2026-03-04 05:06:30'));
        $this->assertSame('2026-03-04 06:00', $next('0 * * * *', '2026-03-04 05:06'));
        $this->assertSame('2026-03-05 03:00', $next('0 3 * * *', '2026-03-04 05:06'));
        $this->assertSame('2026-03-09 09:00', $next('0 9 * * 1', '2026-03-04 05:06'));
        $this->assertSame('2026-12-25 00:00', $next('0 0 25 12 *', '2026-03-04 05:06'));
        $this->assertSame('2028-02-29 00:00', $next('0 0 29 2 *', '2026-03-04 05:06'), 'leap day');
    }

    private function schedule(?\Closure $runner = null): Schedule
    {
        return new Schedule(new Container(), new NullLogger(), new ArrayCache(), $runner);
    }

    public function testFrequencyHelpers(): void
    {
        $s = $this->schedule();
        $this->assertSame('*/5 * * * *', $s->call(fn () => 1)->everyFiveMinutes()->expression());
        $this->assertSame('30 14 * * *', $s->call(fn () => 1)->dailyAt('14:30')->expression());
        $this->assertSame('15 * * * *', $s->call(fn () => 1)->hourlyAt(15)->expression());
        $this->assertSame('0 8 * * 1', $s->call(fn () => 1)->weeklyOn(1, '8:00')->expression());
        $this->assertSame('0 0 15 * *', $s->call(fn () => 1)->monthlyOn(15)->expression());
        $this->assertSame('0 0 * * 1-5', $s->call(fn () => 1)->daily()->weekdays()->expression());
        $this->expectException(\InvalidArgumentException::class);
        $s->call(fn () => 1)->dailyAt('25:00');
    }

    public function testRunsOnlyDueTasksAndIsolatesFailures(): void
    {
        $ran = [];
        $s = $this->schedule();
        $s->call(function () use (&$ran) {
            $ran[] = 'every-minute';
        }, 'a')->everyMinute();
        $s->call(function () use (&$ran) {
            $ran[] = 'noon';
        }, 'b')->dailyAt('12:00');
        $s->call(function () {
            throw new \RuntimeException('boom');
        }, 'c')->everyMinute();
        $s->call(function () use (&$ran) {
            $ran[] = 'after-failure';
        }, 'd')->everyMinute();

        $result = $s->run($this->at('2026-03-04 09:30'));
        $this->assertSame(['every-minute', 'after-failure'], $ran);
        $this->assertSame(['a' => 'ran', 'c' => 'failed', 'd' => 'ran'], $result);

        $s->run($this->at('2026-03-04 12:00'));
        $this->assertContains('noon', $ran);
    }

    public function testFilters(): void
    {
        $n = 0;
        $s = $this->schedule();
        $s->call(function () use (&$n) {
            $n++;
        })->everyMinute()->when(fn () => false);
        $s->call(function () use (&$n) {
            $n += 10;
        })->everyMinute()->skip(fn () => true);
        $s->call(function () use (&$n) {
            $n += 100;
        })->everyMinute()->when(fn () => true);
        $s->run($this->at('2026-03-04 09:30'));
        $this->assertSame(100, $n);
    }

    public function testWithoutOverlappingSkipsWhileRunning(): void
    {
        $s = $this->schedule();
        $inner = null;
        $s->call(function () use (&$inner, &$s) {
            $inner = $s->run(new \DateTimeImmutable('2026-03-04 09:30')); // simulate a second cron tick mid-run
        }, 'long')->everyMinute()->withoutOverlapping();
        $outer = $s->run($this->at('2026-03-04 09:30'));
        $this->assertSame(['long' => 'ran'], $outer);
        $this->assertSame(['long' => 'skipped'], $inner);
        $this->assertSame(['long' => 'ran'], $s->run($this->at('2026-03-04 09:31')), 'lock released afterwards');
    }

    public function testLockIsReleasedEvenWhenTheTaskFails(): void
    {
        $s = $this->schedule();
        $s->call(fn () => throw new \RuntimeException('x'), 't')->everyMinute()->withoutOverlapping();
        $this->assertSame(['t' => 'failed'], $s->run($this->at('2026-03-04 09:30')));
        $this->assertSame(['t' => 'failed'], $s->run($this->at('2026-03-04 09:31')), 'not stuck as "skipped"');
    }

    public function testCommandsAndDependencyInjection(): void
    {
        $lines = [];
        $s = $this->schedule(function (string $line) use (&$lines) {
            $lines[] = $line;
            return str_contains($line, 'fail') ? 2 : 0;
        });
        $s->command('queue:work --stop-when-empty')->everyMinute();
        $s->command('do:fail')->everyMinute();
        $s->call(function (\stdClass $o) use (&$lines) {
            $lines[] = 'di:' . get_class($o);
        }, 'di')->everyMinute();
        $r = $s->run($this->at('2026-03-04 09:30'));
        $this->assertSame(['queue:work --stop-when-empty', 'do:fail', 'di:stdClass'], $lines);
        $this->assertSame('failed', $r['do:fail']);
    }

    public function testScheduleRunAndListCommands(): void
    {
        $this->app->make(Schedule::class)->call(function () {
            $GLOBALS['__naluz_ran'] = true;
        }, 'cli-task')->everyMinute();
        $stream = fopen('php://memory', 'w+');
        $kernel = new Kernel($this->app, new Output($stream));
        $this->assertSame(0, $kernel->run(['naluz', 'schedule:run']));
        $this->assertTrue($GLOBALS['__naluz_ran']);
        $this->assertSame(0, $kernel->run(['naluz', 'schedule:list']));
        rewind($stream);
        $out = (string) stream_get_contents($stream);
        $this->assertStringContainsString('[ran]  cli-task', $out);
        $this->assertStringContainsString('* * * * *', $out);
        unset($GLOBALS['__naluz_ran']);
    }
}
