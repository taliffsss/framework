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

Verified, not just claimed: `tests/Unit/PsrComplianceTest.php` asserts the interface for each row, and
`vendor/bin/phpcs` checks the coding standard (config in `phpcs.xml.dist`, runs in CI).

| PSR | | Status |
|---|---|---|
| 1 Basic Coding Standard | style | **0 errors** under `phpcs` (PSR-1 is part of the PSR-12 ruleset). Test files may hold several small fixture classes |
| 3 Logger | `Log\FileLogger` | implemented (extends `Psr\Log\AbstractLogger`); the container serves `LoggerInterface` |
| 4 Autoloading | `composer.json` | `Naluz\` → `src/`, `App\` → `app/`, `Database\Factories\|Seeders\`; a test checks every class file matches its namespace |
| 6 Caching Interface | `Cache\Psr6\CacheItemPool` | implemented as an adapter over any PSR-16 cache (file, array, Redis); container serves `CacheItemPoolInterface` |
| 7 HTTP Message | via `nyholm/psr7` | requests and responses are PSR-7 objects; `Naluz\Http\Response` extends the nyholm response |
| 11 Container | `Container\Container` | implemented, with auto-wiring, singletons, aliases, method injection |
| 12 Extended Coding Style | style | **0 errors**; 299 *warnings* remain — all "line exceeds the 120-character soft limit" (PSR-12 makes 120 a soft limit) |
| 13 Hypermedia Links | `Http\Link`, `Http\LinkProvider` | implemented (evolvable link + provider), RFC 8288 `Link:` header serialisation with injection checks, `Paginator::links()` |
| 14 Event Dispatcher | `Events\Dispatcher` | dispatcher **and** listener provider, stoppable events |
| 15 HTTP Handlers | `Routing\Router`, `Pipeline`, all middleware | the router is a `RequestHandlerInterface`; every built-in middleware is a PSR-15 `MiddlewareInterface` |
| 16 Simple Cache | `Cache\FileCache`, `ArrayCache`, `Redis\RedisCache` | implemented |
| 17 HTTP Factories | via `nyholm/psr7` | `Psr17Factory` is bound for request, response, stream, URI and uploaded-file creation |
| 18 HTTP Client | `guzzlehttp/guzzle` | Guzzle is the bound `Psr\Http\Client\ClientInterface`; `Http` wrapper adds JSON, safe redirects and SSRF protection — see [http-client.md](http-client.md) |
| 20 Clock | `Support\SystemClock`, `FrozenClock` | implemented; `now()` and the container use it |

(PSR-2, PSR-5, PSR-8, PSR-9, PSR-10, PSR-19, PSR-21 are abandoned, drafts, or withdrawn, and are not applicable.)

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
| Size / dependencies | ~12k LOC, three runtime packages (`nyholm/psr7`, `nyholm/psr7-server`, `guzzlehttp/guzzle`) + PSR interfaces; own Redis client, mailer, template engine | hundreds of packages |
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
