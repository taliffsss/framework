<?php

declare(strict_types=1);

return [
    // Transparent query caching for models. Off by default; MODEL_CACHING=true turns it on for every model
    // (opt a model out with `protected bool $cache = false;`).
    'enabled' => filter_var(env('MODEL_CACHING', false), FILTER_VALIDATE_BOOLEAN),

    // local (files in storage/cache/models) | redis (REDIS_* settings in config/redis.php) | array (per request; tests)
    'driver' => env('MODEL_CACHE_DRIVER', 'local'),

    // How long a cached query may live: 300 s = 5 minutes. This is the safety net for changes this app cannot see
    // (other services, DB triggers, manual SQL); changes made through this app invalidate immediately.
    'ttl' => (int) env('MODEL_CACHE_TTL', 300),

    // Re-cache: after new or changed data is committed, recently cached queries on the affected tables are re-run and
    // stored again, so the next reader gets a warm, fresh entry instead of hitting the database.
    'recache' => filter_var(env('MODEL_CACHE_RECACHE', true), FILTER_VALIDATE_BOOLEAN),
    'recache_limit' => (int) env('MODEL_CACHE_RECACHE_LIMIT', 20),      // distinct recent queries remembered / re-run per write
    'recache_debounce' => (int) env('MODEL_CACHE_RECACHE_DEBOUNCE', 2), // seconds: a burst of writes re-caches once

    // With read replicas: cache misses and re-caching read the PRIMARY, so replication lag can never put stale rows
    // into the cache. Reads that hit the cache never touch any database.
    'read_from_primary' => filter_var(env('MODEL_CACHE_READ_FROM_PRIMARY', true), FILTER_VALIDATE_BOOLEAN),

    // If the cache store is down (e.g. Redis), keep serving from the database instead of failing requests.
    'fallback' => filter_var(env('MODEL_CACHE_FALLBACK', true), FILTER_VALIDATE_BOOLEAN),

    'prefix' => 'naluz_mc_',  // PSR-16 keys may not contain : { } ( ) / \\ @

    // Tables whose models are never cached.
    'exclude_tables' => ['migrations', 'jobs', 'failed_jobs', 'sessions'],

    // 'all' (default): DELETE/TRUNCATE flush everything, because foreign-key cascades change tables the statement
    // never names. 'table': only the named table (use only if you have no cascades or DB triggers).
    'flush_on_delete' => env('MODEL_CACHE_FLUSH_ON_DELETE', 'all'),

    // Encrypt cached rows with APP_KEY (rows may contain personal data; recommended for shared Redis).
    'encrypt' => filter_var(env('MODEL_CACHE_ENCRYPT', false), FILTER_VALIDATE_BOOLEAN),
];
