# Model caching

Automatic, transparent caching of ORM queries — switched on by one line in `.env`:

```env
MODEL_CACHING=true            # off by default
MODEL_CACHE_DRIVER=redis      # local (files in storage/cache/models, default) | redis
MODEL_CACHE_TTL=3600          # safety-net seconds (see "What it can't know")
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
User::query()->cacheFor(60)->get();          // this query only
$user->fresh();  $user->refresh();           // always read live
User::flushCache();                          // drop everything cached for the users table
```

```bash
php naluz model-cache:flush      # invalidate and delete everything
php naluz model-cache:prune      # delete expired files (local driver)
```

`config/model_cache.php` also has `exclude_tables` (default: `migrations`, `jobs`, `failed_jobs`, `sessions`) and `prefix`.

## What it can't know — read this

The cache sees writes made **through this application's database connections**. Changes it can't see are bounded only by the TTL:

- another application or service writing to the same database,
- database triggers, scheduled SQL, `ON UPDATE CASCADE`, manual edits in a SQL client,
- replication lag if you read from replicas.

Lower `MODEL_CACHE_TTL` (or set `$cache = false` on those models) when that matters, or call `model-cache:flush` after
out-of-band changes. Also avoid it where results depend on per-session database state (e.g. PostgreSQL row-level security
using session variables), since cache entries are shared across users.

## Security

- Cached rows are copies of database rows: protect Redis like the database (password, private network), or set
  `MODEL_CACHE_ENCRYPT=true` — rows are then encrypted (XChaCha20-Poly1305, `APP_KEY`) and a tampered entry is treated as a
  cache miss, never trusted.
- Entries are read back with `allowed_classes => false`, so a compromised cache store can't inject objects.
- Keys are hashed; they contain no data. Exclude models holding secrets with `protected bool $cache = false`.

## Debugging

`app(Naluz\Database\ModelCache::class)->hits` / `->misses` count cache hits and misses in the current process.
