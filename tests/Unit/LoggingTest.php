<?php

declare(strict_types=1);

namespace Naluz\Tests\Unit;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Naluz\Config\Repository;
use Naluz\Http\Client\Http;
use Naluz\Http\Client\HttpClientFactory;
use Naluz\Log\FileLogger;
use Naluz\Log\JsonFormatter;
use Naluz\Log\LogManager;
use Naluz\Log\SlackLogger;
use Naluz\Log\StackLogger;
use Naluz\Log\StreamLogger;
use Naluz\Tests\TestCase;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;

final class LoggingTest extends TestCase
{
    private string $dir;
    private string $errlog;
    /** @var false|string */
    private $oldErrLog;
    /** @var list<array{request:\Psr\Http\Message\RequestInterface}> */
    private array $history = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/naluz-logs-' . bin2hex(random_bytes(4));
        $this->errlog = tempnam(sys_get_temp_dir(), 'errlog');
        $this->oldErrLog = ini_set('error_log', $this->errlog);
    }

    protected function tearDown(): void
    {
        ini_set('error_log', (string) $this->oldErrLog);
        @unlink($this->errlog);
        foreach (glob($this->dir . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->dir);
        parent::tearDown();
    }

    private function manager(array $channels, string $default = 'a'): LogManager
    {
        $cfg = $this->app->make(Repository::class);
        $cfg->set('logging.default', $default);
        $cfg->set('logging.channels', $channels);
        $manager = new LogManager($this->app, $cfg);
        $this->app->instance(LogManager::class, $manager);
        return $manager;
    }

    private function slackHttp(array $responses): Http
    {
        $this->history = [];
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));
        $f = new Psr17Factory();
        return new Http(HttpClientFactory::make(['handler' => $stack]), $f, $f);
    }

    private function recording(): object
    {
        return new class extends AbstractLogger {
            public array $records = [];

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->records[] = [$level, (string) $message];
            }
        };
    }

    // ---------------------------------------------------------------- local files

    public function testDailyFileWithMinimumLevel(): void
    {
        $log = new FileLogger($this->dir, 'warning');
        $log->info('ignored');
        $log->warning('kept {x}', ['x' => 1]);
        $file = $this->dir . '/naluz-' . date('Y-m-d') . '.log';
        $this->assertFileExists($file);
        $this->assertStringContainsString('WARNING: kept 1', (string) file_get_contents($file));
        $this->assertStringNotContainsString('ignored', (string) file_get_contents($file));
    }

    public function testSingleFileChannel(): void
    {
        $log = new FileLogger($this->dir, 'debug', false, 0, 'app.log');
        $log->error('boom');
        $this->assertFileExists($this->dir . '/app.log');
        $this->assertSame([], glob($this->dir . '/naluz-*.log') ?: []);
    }

    public function testFileNameCannotEscapeTheDirectory(): void
    {
        (new FileLogger($this->dir, 'debug', false, 0, '../../evil.log'))->error('x');
        $this->assertFileExists($this->dir . '/evil.log');
        $this->assertFileDoesNotExist(dirname($this->dir, 2) . '/evil.log');
    }

    public function testDailyRetentionDeletesOldFilesOnly(): void
    {
        mkdir($this->dir);
        file_put_contents($this->dir . '/naluz-2020-01-01.log', 'old');
        touch($this->dir . '/naluz-2020-01-01.log', time() - 40 * 86400);
        file_put_contents($this->dir . '/naluz-2026-01-01.log', 'recent');
        touch($this->dir . '/naluz-2026-01-01.log', time() - 2 * 86400);
        file_put_contents($this->dir . '/other.txt', 'not a log');
        touch($this->dir . '/other.txt', time() - 400 * 86400);

        (new FileLogger($this->dir, 'debug', true, 14))->info('x');
        $this->assertFileDoesNotExist($this->dir . '/naluz-2020-01-01.log');
        $this->assertFileExists($this->dir . '/naluz-2026-01-01.log');
        $this->assertFileExists($this->dir . '/other.txt', 'only naluz-DATE.log files are ever pruned');
    }

    public function testJsonFormatForLogAggregators(): void
    {
        $log = new FileLogger($this->dir, 'debug', false, 0, 'j.log', new JsonFormatter());
        $log->error('User {id} failed', ['id' => 7, 'exception' => new \RuntimeException("boom\nline2"), 'obj' => new \stdClass()]);
        $record = json_decode((string) file_get_contents($this->dir . '/j.log'), true);
        $this->assertSame('error', $record['level']);
        $this->assertSame('User 7 failed', $record['message']);
        $this->assertSame(\RuntimeException::class, $record['exception']['class']);
        $this->assertSame('[stdClass]', $record['context']['obj']);
        $this->assertSame(1, substr_count((string) file_get_contents($this->dir . '/j.log'), "\n"), 'one record per line');
    }

    public function testStreamLogger(): void
    {
        $file = $this->dir . '.stream';
        $log = new StreamLogger($file, 'notice');
        $log->info('no');
        $log->notice('yes');
        $this->assertStringContainsString('NOTICE: yes', (string) file_get_contents($file));
        $this->assertStringNotContainsString('no', str_replace('NOTICE: yes', '', (string) file_get_contents($file)));
        unlink($file);
        $this->expectException(\InvalidArgumentException::class);
        new StreamLogger('relative/path.log');
    }

    // ---------------------------------------------------------------- Slack

    public function testSlackReceivesCriticalAndAboveOnly(): void
    {
        $slack = new SlackLogger('https://hooks.slack.com/services/T/B/X', $this->slackHttp([new GuzzleResponse(200, [], 'ok')]), 'critical', appName: 'Shop (production)');
        $slack->error('not important enough');
        $this->assertCount(0, $this->history);

        $slack->critical('Payment provider {p} is down', ['p' => 'Stripe', 'exception' => new \RuntimeException('timeout')]);
        $this->assertCount(1, $this->history);
        $req = $this->history[0]['request'];
        $this->assertSame('https://hooks.slack.com/services/T/B/X', (string) $req->getUri());
        $body = json_decode((string) $req->getBody(), true);
        $this->assertStringContainsString('CRITICAL', $body['text']);
        $this->assertStringContainsString('Shop (production)', $body['text']);
        $this->assertSame('Payment provider Stripe is down', $body['attachments'][0]['text']);
        $this->assertSame('RuntimeException', $body['attachments'][0]['fields'][0]['value']);
        $this->assertStringNotContainsString('#0', json_encode($body), 'no stack trace by default');
        $this->assertStringNotContainsString('/tests/', json_encode($body), 'no server file paths in the message');
        $this->assertSame('LoggingTest.php:' . $body['attachments'][0]['fields'][1]['value'] === '' ? '' : $body['attachments'][0]['fields'][1]['value'], $body['attachments'][0]['fields'][1]['value']);
    }

    public function testSlackEscapesUserControlledContentToPreventPingsAndLinks(): void
    {
        $slack = new SlackLogger('https://hooks.slack.com/services/T/B/X', $this->slackHttp([new GuzzleResponse(200)]), 'debug');
        $slack->alert('Login by {u}', ['u' => '<!channel> <https://evil.test|click me> & more']);
        $text = json_decode((string) $this->history[0]['request']->getBody(), true)['attachments'][0]['text'];
        $this->assertStringNotContainsString('<!channel>', $text);
        $this->assertStringContainsString('&lt;!channel&gt;', $text);
        $this->assertStringContainsString('&amp; more', $text);
    }

    public function testSlackTraceOnlyWhenEnabled(): void
    {
        $slack = new SlackLogger('https://hooks.slack.com/services/T/B/X', $this->slackHttp([new GuzzleResponse(200)]), 'debug', includeTrace: true);
        $slack->error('x', ['exception' => new \RuntimeException('t')]);
        $this->assertStringContainsString('```', json_decode((string) $this->history[0]['request']->getBody(), true)['attachments'][0]['text']);
    }

    public function testSlackFailuresNeverThrowAndFallBackToErrorLog(): void
    {
        $down = new \GuzzleHttp\Exception\ConnectException('slack unreachable', new \GuzzleHttp\Psr7\Request('POST', 'https://hooks.slack.com/x'));
        $slack = new SlackLogger('https://hooks.slack.com/services/T/B/SECRETTOKEN', $this->slackHttp([$down, new GuzzleResponse(500)]), 'debug');
        $slack->critical('first');
        $slack->critical('second');
        $log = (string) file_get_contents($this->errlog);
        $this->assertStringContainsString('first', $log);
        $this->assertStringContainsString('second', $log);
        $this->assertStringNotContainsString('SECRETTOKEN', $log, 'the webhook URL is a secret and is never logged');
    }

    public function testSlackWebhookMustBeASlackUrl(): void
    {
        foreach (['', 'http://hooks.slack.com/x', 'https://evil.test/hooks.slack.com/', 'https://hooks.slack.com.evil.test/x'] as $url) {
            try {
                new SlackLogger($url, $this->slackHttp([]));
                $this->fail("accepted {$url}");
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    // ---------------------------------------------------------------- manager & stack

    public function testManagerBuildsChannelsFromConfig(): void
    {
        $m = $this->manager([
            'a' => ['driver' => 'single', 'path' => $this->dir, 'file' => 'a.log'],
            'b' => ['driver' => 'daily', 'path' => $this->dir, 'level' => 'error', 'days' => 3],
            'j' => ['driver' => 'single', 'path' => $this->dir, 'file' => 'j.log', 'format' => 'json'],
            'n' => ['driver' => 'null'],
        ]);
        $m->info('to default');                     // default channel = a
        $m->channel('b')->info('filtered out');
        $m->channel('b')->error('to b');
        $m->channel('j')->warning('json');
        $m->channel('n')->critical('nowhere');

        $this->assertStringContainsString('to default', (string) file_get_contents($this->dir . '/a.log'));
        $b = (string) file_get_contents($this->dir . '/naluz-' . date('Y-m-d') . '.log');
        $this->assertStringContainsString('to b', $b);
        $this->assertStringNotContainsString('filtered out', $b);
        $this->assertSame('json', json_decode((string) file_get_contents($this->dir . '/j.log'), true)['message']);
        $this->assertSame($m->channel('a'), $m->channel('a'), 'channels are built once');
    }

    public function testStackChannelWritesToEveryMemberAndSurvivesAFailingOne(): void
    {
        $broken = new class extends AbstractLogger {
            public function log($level, string|\Stringable $message, array $context = []): void
            {
                throw new \RuntimeException('member down');
            }
        };
        $good = $this->recording();
        (new StackLogger([$broken, $good]))->error('hello');
        $this->assertSame([['error', 'hello']], $good->records, 'the healthy member still got it');
        $this->assertStringContainsString('member down', (string) file_get_contents($this->errlog));

        $m = $this->manager([
            'a' => ['driver' => 'null'],
            'one' => ['driver' => 'single', 'path' => $this->dir, 'file' => 'one.log'],
            'two' => ['driver' => 'single', 'path' => $this->dir, 'file' => 'two.log'],
            'both' => ['driver' => 'stack', 'channels' => ['one', 'two']],
        ], 'both');
        $m->error('twice');
        $this->assertFileExists($this->dir . '/one.log');
        $this->assertFileExists($this->dir . '/two.log');
        $m->stack(['one'])->warning('adhoc');
        $this->assertStringContainsString('adhoc', (string) file_get_contents($this->dir . '/one.log'));
        $this->assertStringNotContainsString('adhoc', (string) file_get_contents($this->dir . '/two.log'));
    }

    public function testSlackChannelFromConfigUsesTheFrameworkHttpClient(): void
    {
        $m = $this->manager([
            'a' => ['driver' => 'null'],
            'slack' => ['driver' => 'slack', 'url' => 'https://hooks.slack.com/services/T/B/X', 'level' => 'critical'],
        ]);
        $this->assertInstanceOf(SlackLogger::class, $m->channel('slack'));
    }

    public function testCustomDriverAcceptsAnyPsr3Logger(): void
    {
        $mine = $this->recording();
        $m = $this->manager([
            'a' => ['driver' => 'custom', 'via' => fn (array $c) => $mine],
            'bad' => ['driver' => 'custom', 'via' => fn () => new \stdClass()],
        ]);
        $m->warning('via custom');
        $this->assertSame([['warning', 'via custom']], $mine->records);
        $this->expectException(\InvalidArgumentException::class);
        $m->channel('bad');
    }

    public function testUnknownChannelsDriversAndCyclesAreRejected(): void
    {
        $m = $this->manager(['a' => ['driver' => 'null'], 'x' => ['driver' => 'carrier-pigeon'], 'loop' => ['driver' => 'stack', 'channels' => ['loop']]]);
        foreach (['missing', 'x', 'loop'] as $name) {
            try {
                $m->channel($name);
                $this->fail("channel {$name} should be rejected");
            } catch (\InvalidArgumentException | \LogicException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testManagerIsThePsr3LoggerTheContainerHandsOut(): void
    {
        $this->assertInstanceOf(LogManager::class, $this->app->make(LoggerInterface::class));
        $this->assertInstanceOf(LoggerInterface::class, logger());
    }

    public function testInvalidLevelsAreRejected(): void
    {
        $this->expectException(\Psr\Log\InvalidArgumentException::class);
        (new FileLogger($this->dir))->log('shouting', 'x');
    }
}
