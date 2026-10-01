# Changelog

## Unreleased

### Added
- SQL Server (`sqlsrv`) support: dialect, schema builder, MERGE upserts, parameter/row chunking.
- Read/write connections: separate sessions, replica pools (`DB_READ_HOST` list), sticky reads, failover, read-only
  replicas, `useWritePdo()`.
- NoSQL document stores (`file`, `memory`, `mongodb`) with an injection-safe query builder (`docs/nosql.md`).
- `php naluz run:server [--port=8001] [--host] [--workers]` replaces `serve`.
- Compiled, auto-escaping template engine (`*.naluz.php`): `{{ }}` escapes, `{!! !!}` is raw; layouts, sections, stacks,
  includes, loops, `@csrf`, `@method`, `@json`, `@auth`/`@guest`.
- Queues: sync, database and Redis drivers; encrypted JSON payloads, retries with backoff, failed-job table,
  `queue:work/failed/retry/flush`.
- Mail: MIME builder, SMTP (STARTTLS/SSL, AUTH PLAIN/LOGIN), log and array transports, queued sending,
  header-injection protection.
- Scheduler: cron expressions, fluent frequencies, overlap protection, `schedule:run/list`.
- File storage: root-confined local disks and a safe upload helper (content-based type detection).
- Redis: dependency-free client, cache, session handler and queue.
- ORM: polymorphic (`morphOne/morphMany/morphTo`) and through (`hasManyThrough/hasOneThrough`) relations,
  lazy-loading guard (N+1 → exception in local/testing), model factories and seeders.
- Route caching (`route:cache`), `naluz new` project scaffolder, `migrate:fresh`, `db:seed`, `view:clear`,
  Composer package auto-discovery (`extra.naluz.providers`).

### Changed
- Real environment variables now take precedence over `.env`.

## 1.0.0
- Initial NaluzPHP release: container, router, PSR-15 pipeline, query builder, ORM, migrations, validation,
  sessions, auth (session + JWT), CSRF, security headers, CORS, rate limiting, CLI.

## Unreleased (PSR coverage)

### Added
- PSR-6 (`Cache\Psr6\CacheItemPool` over any PSR-16 cache), PSR-13 (`Http\Link`, `LinkProvider`, `Paginator::links()`),
  PSR-18 via Guzzle (`Http` wrapper with JSON helpers, bounded redirects that drop credentials cross-origin, SSRF guard),
  PSR-20 (`SystemClock`, `FrozenClock`).
- `ErrorHandler`: warnings → exceptions, deprecations logged, uncaught exceptions and fatals logged.
- `phpcs.xml.dist` + CI step: PSR-12 enforced (0 errors).

### Changed
- `FileLogger` falls back to `error_log()` when the log file can't be written, instead of dropping the message.
- Code reformatted to PSR-12.

## Unreleased (logging)

### Added
- Channel-based logging (`config/logging.php`): `daily` (with retention), `single`, `stderr`/`stdout`, `errorlog`, `slack`,
  `stack`, `null`, `custom`; per-channel levels; JSON or line format; `logger()->channel('slack')`.
- `SlackLogger`: critical+ by default, escaped content, no traces unless enabled, never throws, webhook URL validated.

### Changed
- `LoggerInterface` now resolves to `LogManager`; `app.log_level` was replaced by `logging.channels.*.level` (`LOG_LEVEL`).

## Unreleased (model cache)

### Added
- Automatic model-level query caching: `MODEL_CACHING=true`, `MODEL_CACHE_DRIVER=local|redis`, `MODEL_CACHE_TTL`,
  `MODEL_CACHE_ENCRYPT`. Table-version invalidation driven by connection-level write detection (query builder, pivots,
  raw SQL, migrations), transaction-safe, delete/DDL flush for FK cascades, raw-SQL queries never cached.
- `withoutCache()`, `cacheFor()`, `Model::flushCache()`, per-model `$cache` / `$cacheTtl`, `model-cache:flush|prune`.
- `Connection::onWrite()` / `DatabaseManager::listenForWrites()` hooks; `FileCache::prune()`.

### Changed (model cache)
- Default TTL is now **300 seconds (5 minutes)** (`MODEL_CACHE_TTL`).

### Added (model cache)
- **Re-caching:** after new/updated/deleted data is committed, recently cached queries on the changed tables are re-run and
  stored again (`MODEL_CACHE_RECACHE`, `_RECACHE_LIMIT`, `_RECACHE_DEBOUNCE`).
- Store-failure fallback (`MODEL_CACHE_FALLBACK`), writes that match no rows invalidate nothing,
  `Model::runWithoutCache()`, `model-cache:flush --model=…`, `Query\Builder::readPlan()`.
