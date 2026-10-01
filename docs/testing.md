# Testing

```bash
vendor/bin/phpunit                       # everything
vendor/bin/phpunit --testsuite Security  # Unit | Database | Http | Security | Console
vendor/bin/phpunit --filter testEagerLoading
```

## What's covered

| Suite | Verifies |
|---|---|
| Unit | container (autowiring, circular deps), support helpers, `.env` parser, validator rules |
| Database | query builder SQL + bindings, joins/aggregates/upsert/pagination/chunk/cursor, nested transactions, **SQL-injection attempts**, schema builder, migrator, ORM CRUD/casts/relations/eager loading (N+1 asserted)/soft deletes/events/mass assignment |
| Http | REST CRUD with validation, status codes (404/405/422/400/429), router features, error rendering with/without debug, views, form-method override, validation redirect + flash |
| Security | CSRF, cross-origin, session cookie flags/fixation/regeneration, auth guards, JWT forgery (`alg:none`, tampering, expiry, issuer), CORS, headers, rate limiting, open redirect, XSS escaping, encryption tamper detection, cache object-injection, log forging, traversal |
| Console | commands, generators, `naluz new` end-to-end, `migrate:fresh`/`db:seed`, package discovery, traversal/code-injection rejection |
| Queue | one contract run against **both** the database and a real Redis server: retries/backoff, failed jobs, forged payloads, crashed-worker redelivery |
| Mail | MIME/encoding, header-injection attacks, SMTP conversation against a scripted fake server (auth, rejection, dot-stuffing), queued sending |
| Storage | CRUD, traversal & symlink escapes, upload content sniffing, disguised scripts, size limits |
| Schedule | cron matching/next-run for ~40 expressions, invalid input, overlap locks, failure isolation, CLI |

The suite was also mutation-checked by hand: disabling CSRF validation, identifier validation or JWT signature checks
makes tests fail.

## Writing tests

Extend `Naluz\Tests\TestCase`. It boots the real application against in-memory SQLite, runs the migrations and gives you
request helpers that go through the **whole** middleware stack:

```php
final class ArticleApiTest extends Naluz\Tests\TestCase
{
    public function testCreate(): void
    {
        $r = $this->json('POST', '/api/articles', ['title' => 'Hi', 'body' => '…']);
        $this->assertSame(201, $r->getStatusCode());
        $this->assertSame('Hi', $this->decode($r)['title']);
        $this->assertSame(1, Article::count());
    }
}
```

Helpers: `get()`, `json()`, `form()`, `send()`, `decode()`, `routes(fn)` (register routes for one test), `db()`,
`queries(fn)` (collect executed SQL), and a cookie jar so sessions persist between requests in one test.
Override `configOverrides()` to change configuration, `migrate()` to skip migrations.

## Redis tests

Redis-backed tests (cache, sessions, queue) start a throw-away `redis-server` on a random port and are **skipped** when
the binary isn't installed (`apt install redis-server`).

## Other databases

The framework suite uses SQLite for speed. To check MySQL/PostgreSQL, point `configOverrides()` at a server and run the
Database suite against it (the tests only use portable features). This is not part of the default CI.
