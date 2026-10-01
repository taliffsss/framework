<?php

declare(strict_types=1);

namespace Naluz\Console\Commands;

use Naluz\Console\Command;
use Naluz\Console\Input;
use Naluz\Console\Output;
use Naluz\Foundation\Application;

final class ServeCommand extends Command
{
    public static function instances(Application $app): array
    {
        return [new self($app)];
    }

    public function name(): string
    {
        return 'serve';
    }

    public function description(): string
    {
        return 'Start the PHP development server (--host=127.0.0.1 --port=8000)';
    }

    public function handle(Input $input, Output $output): int
    {
        $host = (string) $input->option('host', '127.0.0.1');
        $port = (string) $input->option('port', '8000');
        if (!preg_match('/^[A-Za-z0-9.\-]+$/', $host) || !ctype_digit($port)) {
            $output->error('Invalid --host or --port.');
            return 1;
        }
        $output->info("NaluzPHP development server started: http://{$host}:{$port}  (Ctrl+C to stop)");
        $cmd = sprintf('%s -S %s -t %s', escapeshellarg(PHP_BINARY), escapeshellarg("{$host}:{$port}"), escapeshellarg($this->app->basePath('public')));
        passthru($cmd, $code);
        return $code;
    }
}
