<?php

declare(strict_types=1);

use Naluz\Foundation\Application;
use Naluz\Http\Response;
use Naluz\Support\Collection;
use Naluz\Support\Env;

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        return Env::get($key, $default);
    }
}

if (!function_exists('collect')) {
    function collect(iterable $items = []): Collection
    {
        return Collection::make($items);
    }
}

if (!function_exists('app')) {
    /** Resolve the application, or a service from its container. */
    function app(?string $abstract = null): mixed
    {
        $app = Application::getInstance();
        return $abstract === null ? $app : $app->make($abstract);
    }
}

if (!function_exists('config')) {
    function config(?string $key = null, mixed $default = null): mixed
    {
        $config = app(\Naluz\Config\Repository::class);
        return $key === null ? $config : $config->get($key, $default);
    }
}

if (!function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        return app()->basePath($path);
    }
}

if (!function_exists('storage_path')) {
    function storage_path(string $path = ''): string
    {
        return app()->basePath('storage' . ($path !== '' ? DIRECTORY_SEPARATOR . ltrim($path, '/\\') : ''));
    }
}

if (!function_exists('e')) {
    /** HTML-escape a value (use for every piece of untrusted output in views). */
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('response')) {
    function response(string $body = '', int $status = 200, array $headers = []): Response
    {
        return new Response($status, $headers, $body);
    }
}

if (!function_exists('json')) {
    function json(mixed $data, int $status = 200, array $headers = []): Response
    {
        return Response::json($data, $status, $headers);
    }
}

if (!function_exists('redirect')) {
    function redirect(string $to, int $status = 302): Response
    {
        return Response::redirect($to, $status);
    }
}

if (!function_exists('view')) {
    function view(string $name, array $data = [], int $status = 200): Response
    {
        return new Response($status, ['Content-Type' => 'text/html; charset=UTF-8'], app(\Naluz\View\Factory::class)->render($name, $data));
    }
}

if (!function_exists('route')) {
    function route(string $name, array $params = [], bool $absolute = false): string
    {
        return app(\Naluz\Routing\Router::class)->url($name, $params);
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return app(\Naluz\Security\Csrf::class)->token();
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
    }
}

if (!function_exists('now')) {
    function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone(config('app.timezone', 'UTC')));
    }
}
