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
`View` · `Cache` · `Log` · `Events` · `Console` · `Support`

## Compared with Laravel

Laravel is the benchmark for developer experience, so naming and ergonomics are deliberately similar
(`Model::with()`, `$router->apiResource()`, `php naluz migrate`…). Differences, honestly stated:

| | NaluzPHP | Laravel |
|---|---|---|
| Size / dependencies | ~5k LOC, 2 runtime deps + PSR interfaces | hundreds of packages, far larger surface |
| HTTP layer | PSR-7/15 end to end (any PSR-15 middleware drops in) | Symfony HttpFoundation (PSR-7 via bridge) |
| Mass assignment | **deny by default** (`$fillable` empty) | deny by default as well (`$guarded = ['*']`), but easily disabled with `$guarded = []` |
| Identifiers in query builder | validated; non-identifiers throw | column names are quoted but not validated, so user-controlled column names are a known injection class |
| Debug | off unless enabled | `APP_DEBUG` set by the skeleton's `.env.example` to `true` |
| Security headers / CSP, CORS allow-list | on by default | opt-in via packages/middleware |
| Templating | plain PHP + layouts (explicit `e()`) | Blade (auto-escaping, compiled) |
| ORM | Active record, 4 relation types, eager loading, soft deletes, casts, events, scopes | far broader (polymorphic, through, morph maps, observers, factories…) |
| Ecosystem | none yet | vast (queues, Horizon, Sanctum, Cashier, Nova…) |
| Queues, mail, broadcasting, scheduler, file storage | not included | included |

NaluzPHP does **not** try to match Laravel feature-for-feature; it targets the core of monoliths and JSON APIs with a
smaller, auditable codebase and stricter defaults.

## Extending

- Swap any service by binding it in a provider (`$this->app->singleton(CacheInterface::class, fn () => new MyRedisCache())`).
- Use any PSR-15 middleware from Packagist in route or global stacks.
- Add database drivers by extending `Query\Grammar` / `Schema\Schema` (currently `mysql`, `pgsql`, `sqlite`).

## Known limitations / roadmap

- Not yet included: queues, mail, file storage/uploads, scheduler, Redis cache/session drivers, polymorphic and
  has-many-through relations, route caching, a templating compiler.
- MySQL/PostgreSQL grammars are not run in CI yet (SQLite only).
- Fixed-window rate limiting (simple, slightly bursty at window edges).
