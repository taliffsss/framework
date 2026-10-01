# NaluzPHP

A modern, secure PHP framework for **monolithic apps and REST APIs**, with a built-in **ORM** and an independent
**query builder**. Created by Mark Anthony Naluz.

```php
// routes/api.php
$router->apiResource('posts', PostController::class);

// app/Http/Controllers/Api/PostController.php
public function index(): Paginator
{
    return Post::published()->with('author')->latest()->paginate(15);
}
```

- **PHP 8.2+** (developed and tested on 8.3; CI matrix covers 8.2 – 8.5), `declare(strict_types=1)` everywhere
- **PSR-native**: PSR-1, 3, 4, 6, 7, 11, 12, 13, 14, 15, 16, 17, 18 (Guzzle) and 20 — each one tested (see [architecture](docs/architecture.md))
- **Secure by default**: parameterised SQL with identifier allow-listing, mass-assignment protection, CSRF, hardened
  headers, Argon2id, authenticated encryption, strict sessions, rate limiting, CORS allow-list
- **Batteries included, still small**: ORM + query builder, compiled auto-escaping templates, queues, mail, scheduler,
  file storage, Redis cache/session/queue, factories & seeders, route cache — ~11k lines, three runtime packages beyond
  the PSR interfaces (`nyholm/psr7`, `nyholm/psr7-server`, `guzzlehttp/guzzle`)

## Quick start

```bash
git clone https://github.com/taliffsss/framework.git naluzphp && cd naluzphp
composer install
php naluz new my-app                              # scaffolds a fresh project: .env, APP_KEY, JWT_SECRET, composer install
cd ../my-app
php naluz migrate && php naluz run:server              # http://127.0.0.1:8000

# …or work directly in the clone:  cp .env.example .env && php naluz key:generate --jwt && touch storage/database.sqlite
```

Requirements: PHP ≥ 8.2 with `pdo`, `mbstring`, `openssl`, `sodium` (plus `pdo_sqlite`, `pdo_mysql` or `pdo_pgsql`).
Point your web server's document root at `public/` — never at the project root.

Try it:

```bash
curl localhost:8000/api/ping
curl -X POST localhost:8000/api/posts -H 'Content-Type: application/json' -d '{"title":"x"}'   # 422 + errors
php naluz route:list
```

## Project layout

```
app/            your code (PSR-4 namespace App\)   Http/Controllers, Http/Middleware, Models
bootstrap/      creates the Application
config/         app, database, auth, session, security
database/       migrations
public/         the only web-accessible directory (index.php)
resources/views plain-PHP templates with layouts
routes/         web.php (sessions + CSRF) and api.php (stateless, /api prefix, rate limited)
vendor/naluz/framework  the framework itself (namespace Naluz\), installed by Composer from https://github.com/taliffsss/naluz-framework
storage/        logs, cache, sessions, sqlite file
tests/          PHPUnit suite
naluz           command-line entry point
```

## A tour

### Routing & controllers

```php
$router->get('/users/{id}', [UserController::class, 'show'])->where('id', '[0-9]+')->name('users.show');
$router->get('/files/{path?}', fn (?string $path = null) => ['path' => $path]);
$router->prefix('admin')->middleware(['auth', 'throttle:30,1'])->group(function ($router) {
    $router->resource('users', UserController::class);
});
```

Controllers are resolved by the container (constructor **and** method injection). Route parameters are cast to the
declared type (`int $id`); a non-numeric id is a 404 and never reaches your code. Return a PSR-7 response, an array /
model / paginator (→ JSON), a string (→ HTML) or `null` (→ 204).

### Query builder (standalone)

```php
$db = app(Naluz\Database\DatabaseManager::class)->connection();

$rows = $db->table('orders')
    ->join('users', 'users.id', '=', 'orders.user_id')
    ->select('users.name', 'orders.total')
    ->where('orders.total', '>', 100)
    ->whereIn('users.role', ['admin', 'staff'])
    ->orderBy('orders.total', 'desc')
    ->paginate(20, page: 2);

$db->table('users')->upsert([['email' => 'a@x.io', 'name' => 'A']], uniqueBy: ['email'], update: ['name']);

$db->transaction(function ($db) {          // nested calls use savepoints
    $db->table('accounts')->where('id', 1)->decrement('balance', 50);
    $db->table('accounts')->where('id', 2)->increment('balance', 50);
});
```

### ORM

```php
class Post extends Model
{
    use SoftDeletes;
    protected array $fillable = ['user_id', 'title', 'body'];     // mass assignment is OFF until you list fields
    protected array $casts = ['published' => 'bool', 'meta' => 'array', 'status' => Status::class];

    public function author(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); }
    public function tags(): BelongsToMany { return $this->belongsToMany(Tag::class); }
    public function scopePublished(Builder $q): void { $q->where('published', true); }
}

$post = Post::create(['user_id' => 1, 'title' => 'Hello', 'body' => '…']);
$posts = Post::published()->with('author', 'tags')->latest()->paginate(10);   // 3 queries, not 1+2N
$post->tags()->sync([1, 2, 3]);
```

Relationships: `hasOne`, `hasMany`, `belongsTo`, `belongsToMany` (with `attach/detach/sync`). Plus eager loading with
constraints and nesting (`with('posts.comments')`), casts (`int float bool string array json datetime date enum encrypted hashed`),
accessors/mutators, dirty tracking, timestamps, soft deletes, query scopes, model events and `chunk()/cursor()`.

### Validation

```php
$data = Validator::make(Request::input($request), [
    'email' => 'required|email|unique:users,email',
    'items.*.qty' => 'required|integer|min:1',
], db: $connection)->validate();   // throws ValidationException → 422 JSON, or redirect-back for browsers
```

### Authentication

Session guard (`Naluz\Auth\Auth`, middleware `auth`) for web apps; stateless HS256 **JWT** (`Naluz\Security\Jwt`,
middleware `jwt`) for APIs.

### Templates (`resources/views/*.naluz.php`)

```
@extends('layouts/app')
@section('content')
    <h1>{{ $post->title }}</h1>                 {{-- escaped automatically; {!! $html !!} for raw --}}
    @forelse ($post->comments as $c) <p>{{ $c->body }}</p> @empty <p>No comments.</p> @endforelse
    <form method="POST">@csrf @method('PUT') …</form>
@endsection
```

Compiled to plain PHP and cached. See [docs/templates.md](docs/templates.md).

### Queues, mail, scheduler, storage

```php
SendWelcomeEmail::dispatch($user->id);                                   // queue (sync | database | redis), encrypted payloads, retries
$mailer->queue($mailer->message()->to($email)->subject('Hi')->text('…')); // SMTP / log / array transports
$schedule->job(new PruneOldRecords())->dailyAt('03:00')->withoutOverlapping();   // routes/console.php + one cron line
$path = Uploads::store($file, storage('public'), 'avatars', Uploads::IMAGES);    // content-sniffed, random name, allow-list required
```

### Model caching — one `.env` flag

```env
MODEL_CACHING=true
MODEL_CACHE_DRIVER=redis      # or local
```

Every model query is cached for **5 minutes** and, when data is created, updated or deleted, the affected entries are
invalidated and **re-cached** automatically (including query-builder writes, pivots, soft deletes, cascades, transactions).
Falls back to the database if the cache store is down. Per-model opt-out with `protected bool $cache = false;`. See [docs/model-cache.md](docs/model-cache.md).

**Databases:** SQLite, MySQL, PostgreSQL, SQL Server, read/write replica splitting, and NoSQL document stores (file, memory, MongoDB) — see [docs/database.md](docs/database.md) and [docs/nosql.md](docs/nosql.md).

### Factories & seeders

```php
Post::factory(5)->published()->create(['user_id' => $user->id]);
php naluz migrate:fresh --seed
```

### CLI

| Command | |
|---|---|
| `new <name>` | scaffold a new project (`--name=vendor/pkg --no-install --dir=…`) |
| `run:server [--port=8001 --host --workers=N]` | development server (default http://127.0.0.1:8000) |
| `key:generate [--jwt] [--show]` | create `APP_KEY` / `JWT_SECRET` |
| `migrate` · `migrate:rollback [--step=N]` · `migrate:status` · `migrate:fresh [--seed]` · `db:seed` | database |
| `make:controller / model / middleware / migration / factory / seeder / job / provider Name` | generators |
| `queue:work` · `queue:failed` · `queue:retry <id\|all>` · `queue:flush` | queues |
| `schedule:run` · `schedule:list` | scheduler |
| `route:list` · `route:cache` · `route:clear` · `view:clear` · `model-cache:flush` · `model-cache:prune` | routing & caches |

## Security defaults

| Threat | Built-in protection |
|---|---|
| SQL injection | Prepared statements with real server-side binding (`ATTR_EMULATE_PREPARES=false`); column/table names that are not plain identifiers, unknown operators and bad sort directions **throw** instead of being escaped; `Connection::raw()` is the explicit escape hatch; bound values never appear in exception messages |
| Mass assignment | `$fillable` is empty by default; `fill()` / `create()` silently drop everything else; validators return only the fields that have rules |
| CSRF | Synchroniser token in every `web` route + `Origin` check; `hash_equals` comparison |
| XSS | `e()` / `$this->e()` in views; JSON responses are encoded with `JSON_HEX_*`; error pages escape messages; hardened CSP default |
| Session attacks | HttpOnly + SameSite=Lax (+ Secure on HTTPS) cookies, 160-bit IDs, unknown client IDs are discarded (no fixation), ID regeneration on login, JSON (not `unserialize`) storage |
| Passwords | Argon2id (bcrypt fallback), transparent rehash, constant-time-ish login path that hashes even for unknown users, 4 KB input cap |
| Encryption | XChaCha20-Poly1305 (libsodium) with key rotation; `encrypted` model cast |
| Tokens | JWT with pinned algorithm (`alg:none` rejected), `hash_equals`, `exp`/`nbf`/`iss` checks, ≥32-byte secret enforced |
| Clickjacking / sniffing / referrer leaks | `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`, COOP, HSTS on HTTPS |
| Brute force / abuse | `throttle:max,minutes` middleware (api group: 60/min) |
| CORS | Explicit origin allow-list, never reflects arbitrary origins, never `*` with credentials |
| Open redirect / header injection | validation redirects only to same-host paths; `redirect()` rejects CR/LF |
| Info leaks | `debug=false` unless enabled; production errors are generic and logged; `X-Powered-By` removed; log-forging newlines stripped |
| Unsafe deserialisation | file cache uses `allowed_classes => false` |
| Path traversal | strict view-name, session-id, cache-key and generator-name allow-lists; storage disks reject `..`/symlink escapes; cache files are hashed |
| Queue / job tampering | payloads are encrypted JSON (no `unserialize`) and must decode to a `Job` subclass |
| Mail header injection | CR/LF/NUL in addresses, subjects, headers → exception before sending |
| Malicious uploads | content-sniffed type, mandatory allow-list, extension from your list, random file name |
| N+1 queries | lazy-load guard throws in local/testing |

See [docs/security.md](docs/security.md) for details and what the framework deliberately does **not** do for you.

## Testing

```bash
composer install
vendor/bin/phpunit            # ~300 tests: unit, database, HTTP, security, console, queue, mail, storage, schedule
```

Tests run against in-memory SQLite and boot the real application, so HTTP tests exercise the whole middleware stack;
Redis tests start a throw-away `redis-server` (skipped if it isn't installed) and the mail tests talk to a scripted fake SMTP server.
See [docs/testing.md](docs/testing.md).

## Documentation

- [Getting started](docs/getting-started.md)
- [Routing, HTTP & views](docs/http.md)
- [Database: query builder, ORM, migrations](docs/database.md)
- [GraphQL](docs/graphql.md) · [Providers & observers](docs/providers-and-observers.md) · [Templates](docs/templates.md) · [Queues](docs/queues.md) · [Mail](docs/mail.md) · [Scheduler](docs/scheduler.md) · [Storage & uploads](docs/storage.md)
- [HTTP client (Guzzle, PSR-18)](docs/http-client.md) · [Logging & error handling](docs/logging.md)
- [Model caching](docs/model-cache.md) · [Performance](docs/performance.md) · [Building packages](docs/packages.md)
- [Security](docs/security.md)
- [Architecture, PSR compliance & how it compares to Laravel](docs/architecture.md)
- [Testing](docs/testing.md)

## License

MIT © Mark Anthony Naluz
