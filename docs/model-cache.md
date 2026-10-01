# Model caching

Automatic, transparent caching of ORM queries — switched on by one line in `.env`:

```env
MODEL_CACHING=true            # off by default
MODEL_CACHE_DRIVER=redis      # local (files in storage/cache/models, default) | redis
MODEL_CACHE_TTL=300           # seconds an entry lives: 300 = 5 minutes (default)
MODEL_CACHE_RECACHE=true      # re-run hot queries after a write so readers get fresh cached data (default)
MODEL_CACHE_ENCRYPT=false     # true = encrypt cached rows with APP_KEY
```

That's all. Every model query (`find`, `first`, `get`/`all`, `where…->get()`, `count`/`sum`/`min`/`max`/`avg`, `exists`, `pluck`,
`value`, `paginate`, eager-loaded relations) is served from the cache after the first run, with **no code changes**:

```php
User::where('email', $email)->first();   // 1st call: database  ·  later calls: cache (zero SQL)
$user->update(['name' => 'New']);        // invalidates automatically
User::where('email', $email)->first();   // fresh data
```

With `MODEL_CACHING` unset or `false` nothing is hooked, nothing is looked up: zero overhead.

## TTL and re-caching

- **Every entry lives 5 minutes** (`MODEL_CACHE_TTL=300`). That is the upper bound on staleness from changes this application
  can't see (see below); changes made through this app are visible immediately.
- **New or updated data is re-cached.** Writes invalidate the affected cached queries at once. Then, as soon as the write is
  *committed*, the framework re-runs the queries that were recently cached on the changed tables and stores the fresh results.
  The first reader after a change therefore hits a warm cache instead of all of them hitting the database at the same moment
  (a "cache stampede"):

```php
$user = User::find(1);              // cached
$user->update(['name' => 'Anna']);  // invalidates, then re-caches the queries that read `users`
User::find(1)->name;                // 'Anna' — served from cache, zero SQL
```

  Every kind of read is re-cached: models, `count`/`sum`/`min`/`max`/`avg`, `exists`, `value`, `pluck`, `paginate`, eager loads.
  The re-cache is checked against a live read in the tests, so a refreshed entry always equals what the database returns.

Guard rails so writes stay cheap:

| Setting | Default | Meaning |
|---|---|---|
| `MODEL_CACHE_RECACHE` | `true` | `false` = invalidate only; the next read repopulates the cache itself |
| `MODEL_CACHE_RECACHE_LIMIT` | `20` | at most this many distinct recent queries are remembered and re-run per write |
| `MODEL_CACHE_RECACHE_DEBOUNCE` | `2` | seconds: a burst of writes re-caches once (later writes still invalidate; reads repopulate lazily) |

Re-caching waits for `COMMIT` (nothing is ever cached from uncommitted data), is skipped for statements that changed no rows,
and is skipped for schema changes. If a remembered query can no longer run, it is dropped silently and the write succeeds.

## If the cache store goes down

With `MODEL_CACHE_FALLBACK=true` (default) a failing store (e.g. Redis unreachable) never fails a request: reads go straight to
the database, writes succeed, and a warning is logged. Set it to `false` to surface cache errors instead. Entries written
before an outage can be served again when the store returns, but never for longer than their 5-minute TTL.

## Drivers

| `MODEL_CACHE_DRIVER` | Storage | Use |
|---|---|---|
| `local` (default) | files in `storage/cache/models` | single server / development. Run `php naluz model-cache:prune` (cron) to delete expired files |
| `redis` | Redis (`REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD`, `REDIS_DB`) | several app servers share one cache, TTLs handled by Redis |
| `array` | memory, per request | tests |

## How invalidation works (why it stays correct)

The cache never relies on you remembering to clear it.

1. Every cached query is keyed by connection + SQL + bindings + a **random version token for each table the query reads**
   (joins and subqueries included) and a global token.
2. Any **write** to a table replaces its token, so every cached query that read it becomes unreachable instantly.
3. Writes are detected **at the database connection**, not in model methods — so `Model::save()`, `User::where()->update()`,
   `$db->table('users')->update()`, pivot `attach/detach/sync`, raw `statement()` SQL, soft deletes and migrations all invalidate.
4. **`DELETE`, `TRUNCATE` and schema changes flush the whole model cache.** Foreign-key cascades modify tables the statement
   never names (deleting a user removes their posts); flushing everything is the only way to be sure. If you have no cascades or
   triggers and many deletes, set `MODEL_CACHE_FLUSH_ON_DELETE=table`.
5. **Transactions are safe:** reads inside a transaction bypass the cache (uncommitted data is never cached), and writes
   invalidate again right after `COMMIT`, so a concurrent request can't leave a pre-commit entry behind.
6. The version is read **before** the query runs, so a write that lands mid-query can never be masked.
7. Queries containing raw SQL (`whereRaw`, `selectRaw`, `orderByRaw`…) are **never cached** — their table dependencies are unknown.

Tested by mutation: disabling invalidation, the transaction bypass, the post-commit bump, or flush-on-delete each fails the suite.

## Controlling it

```php
class AuditLog extends Model { protected bool $cache = false; }       // never cache this model
class Country  extends Model { protected ?int $cacheTtl = 86400; }    // longer TTL for reference data

User::query()->withoutCache()->find($id);    // read live, store nothing
Model::runWithoutCache(fn () => Report::all()); // a whole block without caching (writes still invalidate)
User::query()->cacheFor(60)->get();          // this query only
$user->fresh();  $user->refresh();           // always read live
User::flushCache();                          // drop everything cached for the users table
```

```bash
php naluz model-cache:flush      # invalidate and delete everything
php naluz model-cache:flush --model="App\\Models\\Post"   # one model's table only
php naluz model-cache:prune      # delete expired files (local driver)
```

`config/model_cache.php` also has `exclude_tables` (default: `migrations`, `jobs`, `failed_jobs`, `sessions`) and `prefix`.

## What it can't know — read this

The cache sees writes made **through this application's database connections**. Changes it can't see are bounded only by the TTL:

- another application or service writing to the same database (entries are at most 5 minutes old),
- database triggers, scheduled SQL, `ON UPDATE CASCADE`, manual edits in a SQL client,
- replication lag if you read from replicas.

Lower `MODEL_CACHE_TTL` below the default 300 (or set `$cache = false` on those models) when that matters, or call `model-cache:flush` after
out-of-band changes. Also avoid it where results depend on per-session database state (e.g. PostgreSQL row-level security
using session variables), since cache entries are shared across users.

## Security

- Cached rows are copies of database rows: protect Redis like the database (password, private network), or set
  `MODEL_CACHE_ENCRYPT=true` — rows are then encrypted (XChaCha20-Poly1305, `APP_KEY`) and a tampered entry is treated as a
  cache miss, never trusted.
- Entries are read back with `allowed_classes => false`, so a compromised cache store can't inject objects.
- Keys are hashed; they contain no data. Exclude models holding secrets with `protected bool $cache = false`.

## Debugging

`app(Naluz\Database\ModelCache::class)->hits` / `->misses` / `->recached` count hits, misses and re-cached queries in the current process.
