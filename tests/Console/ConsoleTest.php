<?php

declare(strict_types=1);

namespace Naluz\Tests\Console;

use Naluz\Console\Kernel;
use Naluz\Console\Output;
use Naluz\Foundation\Application;
use PHPUnit\Framework\TestCase;

final class ConsoleTest extends TestCase
{
    private string $base;

    protected function setUp(): void
    {
        $this->base = sys_get_temp_dir() . '/naluz-app-' . bin2hex(random_bytes(4));
        mkdir($this->base . '/config', 0775, true);
        file_put_contents($this->base . '/config/app.php', "<?php return ['providers' => []];");
    }

    protected function tearDown(): void
    {
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->base, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) {
            $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
        }
        rmdir($this->base);
    }

    /** @return array{0:int,1:string} */
    private function cli(array $args): array
    {
        $stream = fopen('php://memory', 'w+');
        $code = (new Kernel(new Application($this->base), new Output($stream)))->run(['naluz', ...$args]);
        rewind($stream);
        return [$code, (string) stream_get_contents($stream)];
    }

    public function testListShowsCommands(): void
    {
        [$code, $out] = $this->cli(['list']);
        $this->assertSame(0, $code);
        foreach (['migrate', 'run:server', 'route:list', 'make:model', 'key:generate'] as $name) {
            $this->assertStringContainsString($name, $out);
        }
        $this->assertStringContainsString('NaluzPHP', $out);
    }

    public function testUnknownCommandFails(): void
    {
        [$code, $out] = $this->cli(['nope']);
        $this->assertSame(1, $code);
        $this->assertStringContainsString('not defined', $out);
    }

    public function testKeyGenerate(): void
    {
        [$code, $out] = $this->cli(['key:generate', '--show', '--jwt']);
        $this->assertSame(0, $code);
        $this->assertMatchesRegularExpression('/APP_KEY=base64:[A-Za-z0-9+\/=]{44}/', $out);
        $this->assertMatchesRegularExpression('/JWT_SECRET=[a-f0-9]{64}/', $out);

        file_put_contents($this->base . '/.env', "APP_NAME=x\nAPP_KEY=\n");
        $this->cli(['key:generate']);
        $env = file_get_contents($this->base . '/.env');
        $this->assertMatchesRegularExpression('/^APP_KEY=base64:/m', $env);
        $this->assertStringContainsString('APP_NAME=x', $env);
    }

    public function testMakeGenerators(): void
    {
        $this->assertSame(0, $this->cli(['make:controller', 'Admin/UserController'])[0]);
        $file = $this->base . '/app/Http/Controllers/Admin/UserController.php';
        $this->assertFileExists($file);
        $this->assertStringContainsString('namespace App\Http\Controllers\Admin;', (string) file_get_contents($file));
        exec(PHP_BINARY . ' -l ' . escapeshellarg($file), $o, $rc);
        $this->assertSame(0, $rc, 'generated code is valid PHP');

        $this->assertSame(0, $this->cli(['make:model', 'Comment'])[0]);
        $this->assertStringContainsString('$fillable', (string) file_get_contents($this->base . '/app/Models/Comment.php'));
        $this->assertSame(0, $this->cli(['make:middleware', 'EnsureAdmin'])[0]);
        $this->assertSame(0, $this->cli(['make:migration', 'create_comments_table'])[0]);
        $migration = glob($this->base . '/database/migrations/*_create_comments_table.php');
        $this->assertCount(1, $migration);
        $this->assertStringContainsString("create('comments'", (string) file_get_contents($migration[0]));
        exec(PHP_BINARY . ' -l ' . escapeshellarg($migration[0]), $o, $rc);
        $this->assertSame(0, $rc);

        $this->assertSame(1, $this->cli(['make:model', 'Comment'])[0], 'never overwrites');
    }

    public function testGeneratorsRejectPathTraversalAndCodeInjection(): void
    {
        foreach (['../../evil', '..\\evil', 'Foo; system("id")', 'a b', '/etc/passwd', "X\nY", ''] as $bad) {
            $this->assertSame(1, $this->cli(['make:controller', $bad])[0], "rejected: {$bad}");
        }
        $this->assertFileDoesNotExist(dirname($this->base) . '/evil.php');
        $this->assertDirectoryDoesNotExist($this->base . '/app/Http/Controllers/..');
    }
}
