<?php

declare(strict_types=1);

namespace Naluz\Tests\Support;

use Naluz\Redis\Client;

/** Starts a throw-away redis-server for integration tests; tests are skipped when the binary is missing. */
trait RedisServer
{
    private static mixed $redisProc = null;
    private static int $redisPort = 0;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        $bin = trim((string) shell_exec('command -v redis-server 2>/dev/null'));
        if ($bin === '') {
            return;
        }
        for ($try = 0; $try < 5 && self::$redisProc === null; $try++) {
            self::$redisPort = random_int(20000, 60000);
            $proc = proc_open(
                [$bin, '--port', (string) self::$redisPort, '--save', '', '--appendonly', 'no', '--bind', '127.0.0.1'],
                [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
                $pipes
            );
            for ($i = 0; $i < 50 && is_resource($proc); $i++) {
                $s = @stream_socket_client('tcp://127.0.0.1:' . self::$redisPort, $e, $m, 0.1);
                if ($s) {
                    fclose($s);
                    self::$redisProc = $proc;
                    return;
                }
                usleep(50_000);
            }
            if (is_resource($proc)) {
                proc_terminate($proc);
            }
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (is_resource(self::$redisProc)) {
            proc_terminate(self::$redisProc);
            proc_close(self::$redisProc);
        }
        self::$redisProc = null;
        parent::tearDownAfterClass();
    }

    protected static function redisPort(): int
    {
        return self::$redisPort;
    }

    protected function redis(): Client
    {
        if (self::$redisProc === null) {
            $this->markTestSkipped('redis-server is not installed.');
        }
        $client = new Client('127.0.0.1', self::$redisPort);
        $client->command('FLUSHALL');
        return $client;
    }
}
