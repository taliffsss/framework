# Architecture

## Request lifecycle

```
public/index.php → bootstrap/app.php → Application::boot()  (env, config, providers, routes)
  → PSR-15 pipeline: SecurityHeaders → Cors → MethodOverride            (global, config/app.php)
    → Router: match route → route middleware (web: session, csrf | api: throttle | …)
      → controller (container-resolved)  → response normalised to PSR-7
  exceptions → ExceptionHandler (JSON for APIs / HTML for browsers; logs 5xx)
  → Emitter
```

## PSR compliance

| PSR | Status |
|---|---|
| PSR-1 / PSR-12 | code written to the standards (no automated linter is wired up yet) |
| PSR-3 Logger | `Log\FileLogger` (extends `Psr\Log\AbstractLogger`) |
| PSR-4 Autoloading | `Naluz\` → `src/`, `App\` → `app/` |
| PSR-7 / PSR-17 HTTP messages & factories | via `nyholm/psr7`; `Naluz\Http\Response` extends its response |
| PSR-11 Container | `Container\Container` with auto-wiring, singletons, aliases and method injection |
| PSR-14 Events | `Events\Dispatcher` (dispatcher + listener provider, stoppable events) |
| PSR-15 Middleware / handlers | pipeline, router is a `RequestHandlerInterface`, all built-in middleware are PSR-15 |
| PSR-16 Simple cache | `Cache\FileCache`, `Cache\ArrayCache` |

## Components (`src/`)

`Container` · `Config` · `Foundation` (Application, providers, exception handler, emitter) · `Http` (+ `Middleware`) ·
`Routing` · `Database` (`Query`, `Schema`, `Migrations`, `Orm`) · `Validation` · `Security` · `Session` · `Auth` ·
`View` (+ `Compiler`) · `Cache` · `Redis` · `Queue` · `Mail` · `Schedule` · `Storage` · `Log` · `Events` · `Console` · `Support`

## Compared with Laravel

Laravel is the benchmark for developer experience, so naming and ergonomics are deliberately similar
(`Model::with()`, `$router->apiResource()`, `php naluz migrate`, `Job::dispatch()`…). NaluzPHP does **not** claim to be
"more advanced" feature-for-feature — Laravel has far more features and a vast ecosystem. What NaluzPHP offers instead is a
much smaller, auditable codebase with stricter defaults. Honest comparison:

### Where NaluzPHP is deliberately different (or stricter by default)

| | NaluzPHP | Laravel |
|---|---|---|
| Size / dependencies | ~11k LOC, two runtime deps (`nyholm/psr7`, `-server`) + PSR interfaces; own Redis client, mailer, template engine | hundreds of packages |
| HTTP layer | PSR-7/15 end to end (any PSR-15 middleware drops in) | Symfony HttpFoundation (PSR-7 via bridge) |
| Query builder identifiers | validated; non-identifiers throw (user-controlled column names can't inject) | quoted but not validated — a known injection class |
| Debug | off unless explicitly enabled | enabled in the skeleton's `.env.example` |
| Queue payloads | encrypted JSON, class must extend `Job`; no `unserialize` | PHP-serialised; encryption is opt-in per job |
| Uploads | content-sniffed, **mandatory** allow-list, random names | validation rules are opt-in |
| Security headers / CSP, CORS allow-list, CSRF `Origin` check | on by default | opt-in / packages |
| Lazy-load (N+1) guard | on by default in `local`/`testing` | available, you enable it yourself |
| Polymorphic `_type` column | must resolve to a `Model` subclass (+ morph map) | class name stored; morph map optional |

### Where Laravel is ahead (not built here)

- **Queues:** batches, chains, unique jobs, rate limiting, SQS/Beanstalk drivers, Horizon dashboard. (Here: sync/database/redis, retries, backoff, failed jobs.)
- **Mail:** many transports (SES, Mailgun, Postmark…), Markdown mailables, notifications across channels. (Here: SMTP/log/array.)
- **Storage:** S3 and other cloud disks, temporary URLs. (Here: local disks + your own `Filesystem` implementation.)
- **Scheduler:** per-task timezones, background runs, maintenance-mode awareness. (Here: cron expressions, overlap lock.)
- **ORM:** `morphToMany`, pivot models, observers, global scopes, `withCount`/`whereHas`, attribute casting classes, API resources.
- **Templates:** components/slots (`<x-…>`), `@once`, `@class`, `@error`, inheritance of `@parent`. (Here: layouts, sections, stacks, includes.)
- **Route cache** can handle closures there (serialisable closures); here only controller routes can be cached.
- **Everything else:** HTTP client, broadcasting, cache tags/locks, Sanctum/Passport/Socialite/Cashier, Telescope, Livewire/Inertia,
  testing helpers (`assertJson`…), `tinker`, localisation, and the community/ecosystem.

## Roadmap ideas

S3 disk · queue batches/unique jobs · `whereHas`/`withCount` · template components · MySQL/PostgreSQL CI jobs ·
HTTP client · cache tags/locks · API resources.

## Extending

- Swap any service by binding it in a provider (`$this->app->singleton(CacheInterface::class, fn () => new MyRedisCache())`).
- Use any PSR-15 middleware from Packagist in route or global stacks.
- Add database drivers by extending `Query\Grammar` / `Schema\Schema` (currently `mysql`, `pgsql`, `sqlite`).

## Known limitations

- MySQL/PostgreSQL grammars are exercised by SQL-generation tests only; the executable suite runs on SQLite (+ a real Redis server).
- Rate limiting is a fixed window (simple, slightly bursty at window edges).
- The template engine is regex-based: directive arguments containing unbalanced parentheses inside strings are not supported.
