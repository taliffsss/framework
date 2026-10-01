<?php

declare(strict_types=1);

namespace Naluz\Tests\Storage;

use Naluz\Storage\LocalFilesystem;
use Naluz\Storage\StorageException;
use Naluz\Storage\StorageManager;
use Naluz\Storage\UploadRejectedException;
use Naluz\Storage\Uploads;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class StorageTest extends TestCase
{
    private string $dir;
    private LocalFilesystem $disk;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/naluz-store-' . bin2hex(random_bytes(4));
        mkdir($this->dir . '/root', 0775, true);
        file_put_contents($this->dir . '/secret.txt', 'outside the root');
        $this->disk = new LocalFilesystem($this->dir . '/root', '/storage');
    }

    protected function tearDown(): void
    {
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) {
            $f->isDir() && !$f->isLink() ? rmdir($f->getPathname()) : unlink($f->getPathname());
        }
        rmdir($this->dir);
    }

    public function testBasicOperations(): void
    {
        $text = "Hello, this is a plain text file.\nIt has two lines.\n"; // long enough for every libmagic version to detect
        $this->disk->put('a/b/hello.txt', $text);
        $this->assertTrue($this->disk->exists('a/b/hello.txt'));
        $this->assertSame($text, $this->disk->get('a/b/hello.txt'));
        $this->assertSame(strlen($text), $this->disk->size('a/b/hello.txt'));
        $this->assertSame('text/plain', $this->disk->mimeType('a/b/hello.txt'));
        $this->assertEqualsWithDelta(time(), $this->disk->lastModified('a/b/hello.txt'), 5);

        $this->disk->copy('a/b/hello.txt', 'c.txt');
        $this->disk->move('c.txt', 'd/e.txt');
        $this->assertFalse($this->disk->exists('c.txt'));
        $this->assertSame($text, $this->disk->get('d/e.txt'));
        $this->assertSame(['a/b/hello.txt'], $this->disk->files('a/b'));
        $this->assertSame(['a', 'd'], $this->disk->directories());
        $this->assertTrue($this->disk->delete('d/e.txt'));
        $this->assertFalse($this->disk->delete('d/e.txt'));
        $this->assertTrue($this->disk->deleteDirectory('a'));
        $this->assertFalse($this->disk->exists('a'));
        $this->assertSame([], glob($this->dir . '/root/*/*.tmp') ?: []);
    }

    public function testStreamsAndOverwrite(): void
    {
        $h = fopen('php://memory', 'w+');
        fwrite($h, 'streamed');
        rewind($h);
        $this->disk->put('s.txt', $h);
        $this->assertSame('streamed', $this->disk->get('s.txt'));
        $this->disk->put('s.txt', 'v2');
        $this->assertSame('v2', $this->disk->get('s.txt'));
    }

    public function testUrl(): void
    {
        $this->assertSame('/storage/a%20b/c.png', $this->disk->url('a b/c.png'));
        $this->expectException(StorageException::class);
        (new LocalFilesystem($this->dir . '/root'))->url('x'); // private disk has no URL
    }

    #[DataProvider('hostilePaths')]
    public function testPathTraversalIsImpossible(string $path): void
    {
        foreach ([
            fn () => $this->disk->get($path),
            fn () => $this->disk->put($path, 'pwned'),
            fn () => $this->disk->delete($path),
            fn () => $this->disk->exists($path),
            fn () => $this->disk->deleteDirectory($path),
        ] as $op) {
            try {
                $op();
                $this->fail("operation allowed on hostile path: {$path}");
            } catch (StorageException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame('outside the root', file_get_contents($this->dir . '/secret.txt'));
        $this->assertFileDoesNotExist($this->dir . '/pwned');
    }

    public static function hostilePaths(): array
    {
        return [['../secret.txt'], ['a/../../secret.txt'], ['/etc/passwd'], ['..\\secret.txt'], ["a\0b"], ['~/x'], ['C:/Windows/x'], ['a/../..']];
    }

    public function testSymlinksCannotEscapeTheRoot(): void
    {
        symlink($this->dir, $this->dir . '/root/escape');
        $this->expectException(StorageException::class);
        $this->disk->get('escape/secret.txt');
    }

    public function testSymlinkedDirectoryWritesAreRefused(): void
    {
        symlink($this->dir, $this->dir . '/root/escape');
        try {
            $this->disk->put('escape/new.txt', 'x');
            $this->fail();
        } catch (StorageException) {
        }
        $this->assertFileDoesNotExist($this->dir . '/new.txt');
    }

    public function testManagerResolvesConfiguredDisks(): void
    {
        $cfg = new \Naluz\Config\Repository(['filesystems' => ['default' => 'local', 'disks' => ['local' => ['driver' => 'local', 'root' => 'files']]]]);
        $m = new StorageManager($cfg, $this->dir);
        $m->disk()->put('x.txt', '1');
        $this->assertFileExists($this->dir . '/files/x.txt');
        $this->expectException(\InvalidArgumentException::class);
        $m->disk('nope');
    }

    // ---------------------------------------------------------------- uploads

    private function upload(string $content, string $clientName = 'file.bin', string $clientType = 'application/octet-stream', int $error = UPLOAD_ERR_OK): \Psr\Http\Message\UploadedFileInterface
    {
        $f = new Psr17Factory();
        return $f->createUploadedFile($f->createStream($content), strlen($content), $error, $clientName, $clientType);
    }

    private const PNG = "\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x06\x00\x00\x00\x1f\x15\xc4\x89\x00\x00\x00\rIDATx\x9cc\xf8\xff\xff?\x00\x05\xfe\x02\xfe\xa7\x9a\xa0\xa0\x00\x00\x00\x00IEND\xaeB`\x82";

    public function testValidImageIsStoredWithRandomNameAndControlledExtension(): void
    {
        $path = Uploads::store($this->upload(self::PNG, 'cat.png', 'image/png'), $this->disk, 'avatars', Uploads::IMAGES);
        $this->assertMatchesRegularExpression('#^avatars/[0-9a-f]{32}\.png$#', $path);
        $this->assertSame(self::PNG, $this->disk->get($path));
        $this->assertSame('image/png', $this->disk->mimeType($path));
    }

    public function testClientClaimsAreIgnored(): void
    {
        // a PHP script pretending to be a PNG by name and declared type
        $php = '<?php system($_GET["c"]); ?>';
        foreach ([['shell.png', 'image/png'], ['shell.php', 'image/png'], ['../../shell.php', 'text/plain']] as [$name, $type]) {
            try {
                Uploads::store($this->upload($php, $name, $type), $this->disk, 'u', Uploads::IMAGES);
                $this->fail("accepted disguised script as {$name}");
            } catch (UploadRejectedException $e) {
                $this->assertSame(422, $e->status());
            }
        }
        $this->assertSame([], $this->disk->directories(), 'nothing was written');
    }

    public function testRealImageWithPhpExtensionStoredAsPngNotPhp(): void
    {
        $path = Uploads::store($this->upload(self::PNG, 'evil.php', 'application/x-php'), $this->disk, 'u', Uploads::IMAGES);
        $this->assertStringEndsWith('.png', $path);
    }

    public function testHtmlAndSvgAreRejectedByDefaultLists(): void
    {
        foreach (['<html><script>alert(1)</script></html>', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'] as $c) {
            try {
                Uploads::store($this->upload($c, 'x.svg', 'image/svg+xml'), $this->disk, 'u', Uploads::IMAGES);
                $this->fail('script-capable markup accepted');
            } catch (UploadRejectedException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testSizeAndErrorLimits(): void
    {
        foreach ([
            fn () => Uploads::store($this->upload(self::PNG), $this->disk, 'u', Uploads::IMAGES, maxBytes: 10),
            fn () => Uploads::store($this->upload(''), $this->disk, 'u', Uploads::IMAGES),
            fn () => Uploads::store($this->upload(self::PNG, error: UPLOAD_ERR_INI_SIZE), $this->disk, 'u', Uploads::IMAGES),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail();
            } catch (UploadRejectedException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testAllowListIsMandatory(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Uploads::store($this->upload(self::PNG), $this->disk, 'u', []);
    }

    public function testDirectoryArgumentCannotEscape(): void
    {
        $this->expectException(StorageException::class);
        Uploads::store($this->upload(self::PNG), $this->disk, '../../outside', Uploads::IMAGES);
    }
}
