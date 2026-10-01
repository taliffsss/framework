<?php

declare(strict_types=1);

namespace Naluz\Tests\Unit;

use Naluz\Foundation\ErrorHandler;
use Naluz\Log\FileLogger;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

final class ErrorHandlerTest extends TestCase
{
    private int $reporting;

    protected function setUp(): void
    {
        $this->reporting = error_reporting(E_ALL);
    }

    protected function tearDown(): void
    {
        error_reporting($this->reporting);
    }

    private function recordingLogger(): object
    {
        return new class extends AbstractLogger {
            /** @var list<array{0:string,1:string,2:array}> */
            public array $records = [];

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->records[] = [(string) $level, (string) $message, $context];
            }
        };
    }

    public function testWarningsBecomeExceptions(): void
    {
        $h = new ErrorHandler($this->recordingLogger());
        $this->expectException(\ErrorException::class);
        $this->expectExceptionMessage('boom');
        $h->handleError(E_USER_WARNING, 'boom', 'f.php', 3);
    }

    public function testDeprecationsAreLoggedNotThrown(): void
    {
        $log = $this->recordingLogger();
        $this->assertTrue((new ErrorHandler($log))->handleError(E_USER_DEPRECATED, 'old api', 'f.php', 9));
        $this->assertSame('warning', $log->records[0][0]);
        $this->assertSame('old api', $log->records[0][2]['m']);
    }

    public function testSuppressedErrorsAreIgnored(): void
    {
        $previous = error_reporting(0);
        try {
            $this->assertFalse((new ErrorHandler($this->recordingLogger()))->handleError(E_WARNING, 'silenced', 'f.php', 1));
        } finally {
            error_reporting($previous);
        }
    }

    public function testUncaughtExceptionsAreLoggedCritical(): void
    {
        $log = $this->recordingLogger();
        $err = fopen('php://memory', 'w+');
        (new ErrorHandler($log, false, $err))->handleException(new \RuntimeException('db down'));
        rewind($err);
        $this->assertStringNotContainsString('db down', (string) stream_get_contents($err), 'details are not shown unless debug');
        $this->assertSame('critical', $log->records[0][0]);
        $this->assertStringContainsString('RuntimeException', $log->records[0][2]['class']);
        $this->assertInstanceOf(\RuntimeException::class, $log->records[0][2]['exception']);
    }

    public function testFatalErrorsAreLoggedOnShutdown(): void
    {
        $log = $this->recordingLogger();
        $h = new ErrorHandler($log);
        $h->handleShutdown(['type' => E_ERROR, 'message' => 'Allowed memory size exhausted', 'file' => 'x.php', 'line' => 7]);
        $h->handleShutdown(['type' => E_WARNING, 'message' => 'not fatal', 'file' => 'x.php', 'line' => 8]);
        $h->handleShutdown(null);
        $this->assertCount(1, $log->records);
        $this->assertSame('critical', $log->records[0][0]);
    }

    public function testFallsBackToErrorLogWhenTheLoggerItselfFails(): void
    {
        $broken = new class extends AbstractLogger {
            public function log($level, string|\Stringable $message, array $context = []): void
            {
                throw new \RuntimeException('log disk full');
            }
        };
        $sink = tempnam(sys_get_temp_dir(), 'errlog');
        $old = ini_set('error_log', $sink);
        try {
            (new ErrorHandler($broken))->handleShutdown(['type' => E_ERROR, 'message' => 'fatal!', 'file' => 'x.php', 'line' => 1]);
        } finally {
            ini_set('error_log', (string) $old);
        }
        $this->assertStringContainsString('fatal!', (string) file_get_contents($sink));
        unlink($sink);
    }

    public function testRegisterAndUnregister(): void
    {
        $before = set_error_handler(fn () => false);
        restore_error_handler();
        $h = new ErrorHandler($this->recordingLogger());
        $h->register();
        $h->register(); // idempotent
        $installed = set_error_handler(fn () => false);
        restore_error_handler();
        $this->assertIsArray($installed);
        $this->assertSame($h, $installed[0]);
        $h->unregister();
        $after = set_error_handler(fn () => false);
        restore_error_handler();
        $this->assertSame($before, $after, 'the previous handler is restored');
    }

    public function testFileLoggerFallsBackToErrorLogWhenUnwritable(): void
    {
        $sink = tempnam(sys_get_temp_dir(), 'errlog');
        $old = ini_set('error_log', $sink);
        try {
            // a *file* where the log directory should be: mkdir and writes both fail
            $blocker = tempnam(sys_get_temp_dir(), 'blocker');
            (new FileLogger($blocker . '/logs'))->error('cannot reach disk: {why}', ['why' => 'read-only fs']);
        } finally {
            ini_set('error_log', (string) $old);
        }
        $this->assertStringContainsString('cannot reach disk: read-only fs', (string) file_get_contents($sink));
        unlink($sink);
        unlink($blocker);
    }
}
