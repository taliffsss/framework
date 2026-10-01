# Performance

| Feature | How |
|---|---|
| Compiled templates | `*.naluz.php` → plain PHP, cached in `storage/cache/views`; production never re-checks sources (`view:clear` on deploy) |
| Route cache | `php naluz route:cache` writes `storage/cache/routes.php`; boot skips `routes/*.php` entirely. Ignored while `APP_DEBUG=true`. Closures can't be cached → use controller routes (the command lists offenders). `route:clear` removes it |
| Eager loading | `with()` = one query per relation; the **lazy-load guard** turns accidental N+1 into an exception in `local`/`testing` (`PREVENT_LAZY_LOADING=true|false` to override) |
| Model cache | `MODEL_CACHING=true` (+ `MODEL_CACHE_DRIVER=redis\|local`): every ORM query is cached and invalidated automatically on writes — see [model-cache.md](model-cache.md) |
| Redis | `CACHE_DRIVER=redis`, `SESSION_DRIVER=redis`, `QUEUE_CONNECTION=redis` (dependency-free client; `REDIS_HOST/PORT/PASSWORD/DB`) |
| Query helpers | `chunk()` / `cursor()` for big tables, `paginate()` capped at 1000 per page, bulk `insert()` / `upsert()` |
| Opcache | `composer install --no-dev -o`, enable `opcache.validate_timestamps=0` in production and reload php-fpm on deploy |

Deploy checklist:

```bash
composer install --no-dev -o
php naluz migrate
php naluz route:cache && php naluz view:clear
```
