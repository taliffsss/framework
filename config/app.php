<?php

declare(strict_types=1);

use Naluz\Http\Middleware;

return [
    'name' => env('APP_NAME', 'NaluzPHP'),
    'env' => env('APP_ENV', 'production'),
    // Secure by default: debug output must be switched on explicitly.
    'debug' => (bool) env('APP_DEBUG', false),
    // Throw on accidental lazy loading (N+1). Defaults to on for APP_ENV=local|testing or when debugging.
    'prevent_lazy_loading' => env('PREVENT_LAZY_LOADING'),
    // Where `php naluz route:cache` writes the compiled route table (ignored while app.debug is true).
    'routes_cache' => null,
    'url' => env('APP_URL', 'http://localhost'),
    'timezone' => env('APP_TIMEZONE', 'UTC'),
    'key' => env('APP_KEY', ''),
    'previous_keys' => [],
    'cache' => env('CACHE_DRIVER', 'file'),

    /** Composer packages whose auto-discovered providers should NOT be registered (or ['*'] for none). */
    'dont_discover' => [],

    /** Extra service providers: classes extending Naluz\Foundation\ServiceProvider. */
    'providers' => [],

    /** Runs on every request, outermost first. */
    'middleware' => [
        Middleware\SecurityHeaders::class,
        Middleware\Cors::class,
        Middleware\MethodOverride::class,
    ],

    'middleware_aliases' => [
        'session' => Middleware\StartSession::class,
        'csrf' => Middleware\VerifyCsrfToken::class,
        'throttle' => Middleware\Throttle::class,
        'auth' => Middleware\Authenticate::class,
        'jwt' => Middleware\AuthenticateJwt::class,
    ],

    /** `web` wraps routes/web.php; `api` wraps routes/api.php (stateless: no cookies, no CSRF). */
    'middleware_groups' => [
        'web' => ['session', 'csrf'],
        'api' => ['throttle:60,1'],
    ],
];
