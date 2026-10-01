<?php

declare(strict_types=1);

namespace Naluz\Tests\Console;

use Naluz\Console\Kernel;
use Naluz\Console\Output;
use Naluz\Foundation\Application;
use Naluz\Support\Env;
use PHPUnit\Framework\TestCase;

final class RunServerCommandTest extends TestCase
{
    private function run_(array $args): array
    {
        $stream = fopen('php://memory', 'w+');
        $app = new Application(dirname(__DIR__, 2));
        $code = (new Kernel($app, new Output($stream)))->run(['naluz', 'run:server', ...$args]);
        rewind($stream);
        return [$code, (string) stream_get_contents($stream)];
    }

    protected function tearDown(): void
    {
        Env::flush();
    }

    public function testDefaultsToLocalhost8000(): void
    {
        [$code, $out] = $this->run_(['--dry-run']);
        $this->assertSame(0, $code);
        $this->assertStringContainsString("'-S' '127.0.0.1:8000'", $out);
        $this->assertStringContainsString('/public', $out);
    }

    public function testPortAndHostAreConfigurable(): void
    {
        [, $out] = $this->run_(['--port=8001', '--dry-run']);
        $this->assertStringContainsString("'127.0.0.1:8001'", $out);
        [, $out] = $this->run_(['--host=0.0.0.0', '--port=9090', '--dry-run']);
        $this->assertStringContainsString("'0.0.0.0:9090'", $out);
    }

    public function testPortWithSpaceSyntaxWorksToo(): void
    {
        [$code, $out] = $this->run_(['--port', '8002', '--dry-run']);
        $this->assertSame(0, $code);
        $this->assertStringContainsString("'127.0.0.1:8002'", $out);
    }

    public function testEnvironmentProvidesTheDefaults(): void
    {
        Env::set('APP_PORT', '8123');
        Env::set('APP_HOST', '127.0.0.2');
        [, $out] = $this->run_(['--dry-run']);
        $this->assertStringContainsString("'127.0.0.2:8123'", $out);
        [, $out] = $this->run_(['--port=8001', '--dry-run']);
        $this->assertStringContainsString(':8001', $out, 'the flag beats APP_PORT');
    }

    public function testWorkersAreForwardedAsAnEnvironmentVariable(): void
    {
        [$code, $out] = $this->run_(['--workers=4', '--dry-run']);
        $this->assertSame(0, $code);
        $this->assertStringContainsString('PHP_CLI_SERVER_WORKERS=4', $out);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('badInput')]
    public function testInvalidInputIsRejected(array $args): void
    {
        [$code, $out] = $this->run_([...$args, '--dry-run']);
        $this->assertSame(1, $code);
        $this->assertNotSame('', trim($out));
    }

    public static function badInput(): array
    {
        return [
            [['--port=abc']], [['--port=0']], [['--port=70000']], [['--port=-1']], [['--port=80;rm -rf /']],
            [['--host=evil;rm -rf /']], [['--host=a b']], [['--host=$(id)']], [['--workers=0']], [['--workers=999']], [['--workers=x']],
        ];
    }

    public function testBusyPortGivesAFriendlyError(): void
    {
        $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $err);
        $port = (int) substr((string) strrchr((string) stream_socket_get_name($server, false), ':'), 1);
        try {
            [$code, $out] = $this->run_(["--port={$port}"]);
        } finally {
            fclose($server);
        }
        $this->assertSame(1, $code);
        $this->assertStringContainsString('already in use', $out);
        $this->assertStringContainsString('--port=' . ($port + 1), $out);
    }

    public function testOldServeCommandIsGone(): void
    {
        $stream = fopen('php://memory', 'w+');
        $code = (new Kernel(new Application(dirname(__DIR__, 2)), new Output($stream)))->run(['naluz', 'serve']);
        $this->assertSame(1, $code);
    }

    public function testTheRealServerAnswersRequestsOnTheChosenPort(): void
    {
        $probe = stream_socket_server('tcp://127.0.0.1:0');
        $port = (int) substr((string) strrchr((string) stream_socket_get_name($probe, false), ':'), 1);
        fclose($probe);

        $proc = proc_open([PHP_BINARY, dirname(__DIR__, 2) . '/naluz', 'run:server', "--port={$port}"], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes, dirname(__DIR__, 2));
        try {
            $body = false;
            for ($i = 0; $i < 60 && $body === false; $i++) {
                usleep(100_000);
                $body = @file_get_contents("http://127.0.0.1:{$port}/api/ping");
            }
            $this->assertNotFalse($body, 'the development server started and answered');
            $this->assertSame('{"status":"ok","framework":"NaluzPHP"}', $body);
        } finally {
            $status = proc_get_status($proc);
            // stop the php -S child as well as the wrapper
            if ($status['running']) {
                exec('pkill -P ' . (int) $status['pid'] . ' 2>/dev/null');
                proc_terminate($proc);
            }
            proc_close($proc);
        }
    }
}
