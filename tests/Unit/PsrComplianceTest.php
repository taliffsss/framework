<?php

declare(strict_types=1);

namespace Naluz\Tests\Unit;

use Naluz\Cache\ArrayCache;
use Naluz\Cache\Psr6\CacheItemPool;
use Naluz\Container\Container;
use Naluz\Events\Dispatcher;
use Naluz\Http\Link;
use Naluz\Http\LinkProvider;
use Naluz\Http\Response;
use Naluz\Log\FileLogger;
use Naluz\Routing\Router;
use Naluz\Support\SystemClock;
use Naluz\Tests\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Clock\ClockInterface;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Link\EvolvableLinkProviderInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;

/** One assertion per PSR: the framework's own classes implement (and the container wires) the standard interfaces. */
final class PsrComplianceTest extends TestCase
{
    public function testPsr3Logger(): void
    {
        $this->assertInstanceOf(LoggerInterface::class, new FileLogger(sys_get_temp_dir()));
        $this->assertInstanceOf(LoggerInterface::class, $this->app->make(LoggerInterface::class));
    }

    public function testPsr6CacheItemPool(): void
    {
        $this->assertInstanceOf(CacheItemPoolInterface::class, new CacheItemPool(new ArrayCache()));
        $this->assertInstanceOf(CacheItemPoolInterface::class, $this->app->make(CacheItemPoolInterface::class));
    }

    public function testPsr7And17(): void
    {
        $this->assertInstanceOf(ResponseInterface::class, new Response());
        $this->assertInstanceOf(ServerRequestInterface::class, \Naluz\Http\Request::create('GET', '/'));
        $this->assertInstanceOf(RequestFactoryInterface::class, $this->app->make(RequestFactoryInterface::class));
    }

    public function testPsr11Container(): void
    {
        $this->assertInstanceOf(ContainerInterface::class, new Container());
        $this->assertInstanceOf(ContainerInterface::class, $this->app->make(ContainerInterface::class));
    }

    public function testPsr13Links(): void
    {
        $this->assertInstanceOf(EvolvableLinkProviderInterface::class, new LinkProvider());
        $this->assertInstanceOf(\Psr\Link\EvolvableLinkInterface::class, new Link('/x'));
    }

    public function testPsr14Events(): void
    {
        $this->assertInstanceOf(EventDispatcherInterface::class, new Dispatcher());
        $this->assertInstanceOf(EventDispatcherInterface::class, $this->app->make(EventDispatcherInterface::class));
    }

    public function testPsr15Handlers(): void
    {
        $this->assertInstanceOf(RequestHandlerInterface::class, $this->app->make(Router::class));
    }

    public function testPsr16SimpleCache(): void
    {
        $this->assertInstanceOf(CacheInterface::class, new ArrayCache());
        $this->assertInstanceOf(CacheInterface::class, $this->app->make(CacheInterface::class));
    }

    public function testPsr18HttpClient(): void
    {
        $this->assertInstanceOf(ClientInterface::class, $this->app->make(ClientInterface::class));
        $this->assertInstanceOf(\GuzzleHttp\Client::class, $this->app->make(ClientInterface::class), 'Guzzle is the default PSR-18 client');
    }

    public function testPsr20Clock(): void
    {
        $this->assertInstanceOf(ClockInterface::class, new SystemClock());
        $this->assertInstanceOf(ClockInterface::class, $this->app->make(ClockInterface::class));
        $this->assertInstanceOf(\DateTimeImmutable::class, now());
    }

    public function testPsr4AutoloadingMapsNamespacesToDirectories(): void
    {
        $composer = json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/composer.json'), true);
        $this->assertSame('src/', $composer['autoload']['psr-4']['Naluz\\']);
        $this->assertSame('app/', $composer['autoload']['psr-4']['App\\']);
        // every class file's path matches its namespace + name (PSR-4), so the autoloader can find them all
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(dirname(__DIR__, 2) . '/src', \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->getExtension() !== 'php' || $f->getFilename() === 'helpers.php') {
                continue;
            }
            $rel = substr($f->getPathname(), strlen(dirname(__DIR__, 2) . '/src/'), -4);
            $class = 'Naluz\\' . str_replace('/', '\\', $rel);
            $this->assertTrue(class_exists($class) || interface_exists($class) || trait_exists($class) || enum_exists($class), "PSR-4: {$class} not autoloadable");
        }
    }
}
