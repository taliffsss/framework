<?php

declare(strict_types=1);

namespace Naluz\Tests\Console;

use Naluz\Console\Kernel;
use Naluz\Console\Output;
use Naluz\Foundation\Application;
use Naluz\Foundation\PackageManifest;
use Naluz\Tests\TestCase;

final class ProjectCommandsTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmp = sys_get_temp_dir() . '/naluz-proj-' . bin2hex(random_bytes(4));
        mkdir($this->tmp, 0775, true);
    }

    protected function tearDown(): void
    {
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->tmp, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) {
            $f->isDir() && !$f->isLink() ? rmdir($f->getPathname()) : unlink($f->getPathname());
        }
        rmdir($this->tmp);
        parent::tearDown();
    }

    /** @return array{0:int,1:string} */
    private function cli(array $args, ?Application $app = null): array
    {
        $stream = fopen('php://memory', 'w+');
        $code = (new Kernel($app ?? $this->app, new Output($stream)))->run(['naluz', ...$args]);
        rewind($stream);
        return [$code, (string) stream_get_contents($stream)];
    }

    // ---------------------------------------------------------------- naluz new

    public function testNewScaffoldsARunnableProject(): void
    {
        [$code, $out] = $this->cli(['new', 'blog', '--no-install', '--dir=' . $this->tmp, '--name=acme/blog']);
        $this->assertSame(0, $code, $out);
        $p = $this->tmp . '/blog';

        foreach (['composer.json', 'naluz', 'public/index.php', 'config/app.php', 'routes/api.php', 'app/Models/User.php', 'database/migrations', 'resources/views/home.naluz.php', '.env', 'tests/TestCase.php'] as $f) {
            $this->assertFileExists("{$p}/{$f}");
        }
        foreach (['vendor', '.git', 'storage/database.sqlite.bak'] as $f) {
            $this->assertFileDoesNotExist("{$p}/{$f}");
        }
        $this->assertSame('acme/blog', json_decode((string) file_get_contents($p . '/composer.json'), true)['name']);

        $env = (string) file_get_contents($p . '/.env');
        $this->assertMatchesRegularExpression('/^APP_KEY=base64:[A-Za-z0-9+\/=]{44}$/m', $env);
        $this->assertMatchesRegularExpression('/^JWT_SECRET=[a-f0-9]{64}$/m', $env);
        $this->assertStringContainsString('APP_NAME=blog', $env);

        // runtime data from the source project is not carried over
        $this->assertSame([], array_values(array_diff(scandir($p . '/storage/logs') ?: [], ['.', '..', '.gitignore'])));
        $this->assertFileExists($p . '/storage/sessions/.gitignore');

        // end to end: the new project actually works
        symlink(dirname(__DIR__, 2) . '/vendor', $p . '/vendor');
        $run = fn (string $args) => shell_exec('cd ' . escapeshellarg($p) . ' && ' . escapeshellarg(PHP_BINARY) . ' naluz ' . $args . ' 2>&1');
        $this->assertStringContainsString('/api/ping', (string) $run('route:list'));
        $this->assertStringContainsString('Migrated:', (string) $run('migrate'));
        $this->assertStringContainsString('Seeded:', (string) $run('db:seed'));
        unlink($p . '/vendor');
    }

    public function testNewRefusesBadNamesAndExistingDirectories(): void
    {
        foreach (['../evil', 'a/b', '', '.hidden', 'x;rm', 'a b'] as $bad) {
            $this->assertSame(1, $this->cli(['new', $bad, '--no-install', '--dir=' . $this->tmp])[0], "rejected: {$bad}");
        }
        mkdir($this->tmp . '/taken');
        file_put_contents($this->tmp . '/taken/file', 'x');
        $this->assertSame(1, $this->cli(['new', 'taken', '--no-install', '--dir=' . $this->tmp])[0]);
        $this->assertSame('x', file_get_contents($this->tmp . '/taken/file'), 'existing data untouched');
        $this->assertSame(1, $this->cli(['new', 'ok', '--no-install', '--dir=' . $this->tmp, '--name=Bad Name'])[0]);
        $this->assertDirectoryDoesNotExist($this->tmp . '/ok');
    }

    public function testNewRefusesToCopyTheProjectIntoItself(): void
    {
        $inside = dirname(__DIR__, 2) . '/storage/cache';
        $this->assertSame(1, $this->cli(['new', 'recursive', '--no-install', '--dir=' . $inside])[0]);
        $this->assertDirectoryDoesNotExist($inside . '/recursive');
    }

    // ---------------------------------------------------------------- db commands & generators

    public function testMigrateFreshAndSeed(): void
    {
        $this->db()->table('users')->insert(['name' => 'old', 'email' => 'old@x.io', 'password' => 'x']);
        [$code, $out] = $this->cli(['migrate:fresh', '--seed']);
        $this->assertSame(0, $code, $out);
        $this->assertStringContainsString('Dropped all tables', $out);
        $this->assertStringContainsString('Seeded:', $out);
        $this->assertSame(3, \App\Models\User::count(), 'old row gone, seeder rows present');
    }

    public function testProductionGuard(): void
    {
        $this->app->make(\Naluz\Config\Repository::class)->set('app.env', 'production');
        $this->assertSame(1, $this->cli(['migrate:fresh'])[0]);
        $this->assertSame(1, $this->cli(['db:seed'])[0]);
        $this->assertSame(0, $this->cli(['db:seed', '--force'])[0]);
    }

    public function testDbSeedRejectsNonSeederClasses(): void
    {
        $this->assertSame(1, $this->cli(['db:seed', '--class=stdClass'])[0]);
        $this->assertSame(1, $this->cli(['db:seed', '--class=Nope\\Missing'])[0]);
    }

    public function testNewGeneratorsProduceValidPhp(): void
    {
        $base = $this->tmp . '/gen';
        mkdir($base . '/config', 0775, true);
        $app = new Application($base);
        foreach (['factory' => 'WidgetFactory', 'seeder' => 'WidgetSeeder', 'job' => 'SendReport', 'provider' => 'AppServiceProvider'] as $kind => $name) {
            [$code] = $this->cli(["make:{$kind}", $name], $app);
            $this->assertSame(0, $code, $kind);
        }
        foreach (['database/factories/WidgetFactory.php', 'database/seeders/WidgetSeeder.php', 'app/Jobs/SendReport.php', 'app/Providers/AppServiceProvider.php'] as $f) {
            exec(PHP_BINARY . ' -l ' . escapeshellarg("{$base}/{$f}") . ' 2>&1', $o, $rc);
            $this->assertSame(0, $rc, $f);
        }
        $this->assertStringContainsString('namespace Database\Factories;', (string) file_get_contents($base . '/database/factories/WidgetFactory.php'));
        $this->assertStringContainsString('namespace App\Jobs;', (string) file_get_contents($base . '/app/Jobs/SendReport.php'));
    }

    public function testViewClear(): void
    {
        $this->get('/'); // compiles home.naluz.php
        [$code, $out] = $this->cli(['view:clear']);
        $this->assertSame(0, $code);
        $this->assertMatchesRegularExpression('/Removed \d+ compiled/', $out);
    }

    // ---------------------------------------------------------------- package discovery

    private function fakeVendor(array $packages): string
    {
        $vendor = $this->tmp . '/vendor/composer';
        mkdir($vendor, 0775, true);
        file_put_contents($vendor . '/installed.json', json_encode(['packages' => $packages]));
        return $this->tmp . '/vendor';
    }

    public function testDiscoversProvidersFromInstalledPackages(): void
    {
        $vendor = $this->fakeVendor([
            ['name' => 'acme/blog', 'extra' => ['naluz' => ['providers' => [DiscoverableProvider::class]]]],
            ['name' => 'acme/other', 'extra' => ['naluz' => ['providers' => [DiscoverableProvider::class]]]],
            ['name' => 'plain/pkg'],
            ['name' => 'evil/pkg', 'extra' => ['naluz' => ['providers' => [\stdClass::class, 'Nope\\Missing', 123]]]],
        ]);
        $manifest = new PackageManifest($this->tmp, $vendor);
        $this->assertSame([DiscoverableProvider::class], $manifest->providers(), 'only real ServiceProviders, de-duplicated');
        $this->assertSame([], $manifest->providers(['*']));
        $this->assertSame([DiscoverableProvider::class], $manifest->providers(['acme/blog']), 'other package still provides it');
        $this->assertSame([], $manifest->providers(['acme/blog', 'acme/other']));
    }

    public function testRootComposerJsonCanOptPackagesOut(): void
    {
        $vendor = $this->fakeVendor([['name' => 'acme/blog', 'extra' => ['naluz' => ['providers' => [DiscoverableProvider::class]]]]]);
        file_put_contents($this->tmp . '/composer.json', json_encode(['extra' => ['naluz' => ['dont-discover' => ['acme/blog']]]]));
        $this->assertSame([], (new PackageManifest($this->tmp, $vendor))->providers());
    }

    public function testMissingInstalledJsonIsFine(): void
    {
        $this->assertSame([], (new PackageManifest($this->tmp, $this->tmp . '/nope'))->providers());
    }

    public function testDiscoveredProvidersAreBootedByTheApplication(): void
    {
        DiscoverableProvider::$registered = 0;
        $this->fakeVendor([['name' => 'acme/blog', 'extra' => ['naluz' => ['providers' => [DiscoverableProvider::class]]]]]);
        mkdir($this->tmp . '/config');
        $app = new Application($this->tmp);
        $app->boot();
        $this->assertSame(1, DiscoverableProvider::$registered);
        $this->assertSame('from-package', $app->make('acme.service'));
    }
}

final class DiscoverableProvider extends \Naluz\Foundation\ServiceProvider
{
    public static int $registered = 0;

    public function register(): void
    {
        self::$registered++;
        $this->app->bind('acme.service', fn () => 'from-package');
    }
}
