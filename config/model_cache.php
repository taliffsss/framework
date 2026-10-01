<?php

declare(strict_types=1);

return [
    // Transparent query caching for models. Off by default; MODEL_CACHING=true turns it on for every model
    // (opt a model out with `protected bool $cache = false;`).
    'enabled' => filter_var(env('MODEL_CACHING', false), FILTER_VALIDATE_BOOLEAN),

    // local (files in storage/cache/models) | redis (REDIS_* settings in config/redis.php) | array (per request; tests)
    'driver' => env('MODEL_CACHE_DRIVER', 'local'),

    // Safety net for changes this app cannot see (other services, DB triggers, manual SQL). Seconds.
    'ttl' => (int) env('MODEL_CACHE_TTL', 3600),

    'prefix' => 'naluz_mc_',  // PSR-16 keys may not contain : { } ( ) / \\ @

    // Tables whose models are never cached.
    'exclude_tables' => ['migrations', 'jobs', 'failed_jobs', 'sessions'],

    // 'all' (default): DELETE/TRUNCATE flush everything, because foreign-key cascades change tables the statement
    // never names. 'table': only the named table (use only if you have no cascades or DB triggers).
    'flush_on_delete' => env('MODEL_CACHE_FLUSH_ON_DELETE', 'all'),

    // Encrypt cached rows with APP_KEY (rows may contain personal data; recommended for shared Redis).
    'encrypt' => filter_var(env('MODEL_CACHE_ENCRYPT', false), FILTER_VALIDATE_BOOLEAN),
];
