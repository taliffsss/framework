<?php

declare(strict_types=1);

namespace Naluz\Console\Commands;

use Naluz\Cache\FileCache;
use Naluz\Console\Command;
use Naluz\Console\Input;
use Naluz\Console\Output;
use Naluz\Database\ModelCache;
use Naluz\Foundation\Application;

/** model-cache:flush (invalidate everything) / model-cache:prune (delete expired local files). */
final class ModelCacheCommands extends Command
{
    public function __construct(Application $app, private readonly string $kind)
    {
        parent::__construct($app);
    }

    public static function instances(Application $app): array
    {
        return [new self($app, 'model-cache:flush'), new self($app, 'model-cache:prune')];
    }

    public function name(): string
    {
        return $this->kind;
    }

    public function description(): string
    {
        return $this->kind === 'model-cache:flush'
            ? 'Invalidate and delete all cached model queries'
            : 'Delete expired model-cache files (local driver)';
    }

    public function handle(Input $input, Output $output): int
    {
        if ($this->kind === 'model-cache:flush') {
            $cache = $this->app->make(ModelCache::class);
            $cache->flushAll();
            $cache->purge();
            $output->info('Model cache flushed.');
            return 0;
        }
        $dir = $this->app->basePath('storage/cache/models');
        $n = is_dir($dir) ? (new FileCache($dir))->prune() : 0;
        $output->info("Removed {$n} expired file(s).");
        return 0;
    }
}
