# Queues & jobs

Move slow work (mail, reports, webhooks) out of the request.

```bash
php naluz make:job SendWelcomeEmail
```

```php
final class SendWelcomeEmail extends Naluz\Queue\Job
{
    protected int $tries = 5;                       // total attempts
    protected int|array $backoff = [10, 60, 300];   // seconds between attempts (a list = per attempt)

    public function __construct(public readonly int $userId) {}      // public properties = job data

    public function handle(Mailer $mailer): void { /* dependencies are injected */ }

    public function failed(\Throwable $e): void { /* after the last attempt */ }
}

SendWelcomeEmail::dispatch($user->id);                          // anywhere
dispatch((new SendWelcomeEmail($id))->onQueue('mail')->delay(60));
```

## Drivers (`QUEUE_CONNECTION`)

| Driver | Use |
|---|---|
| `sync` (default) | runs immediately in-process; exceptions propagate. Development & tests |
| `database` | `jobs` table (run `php naluz migrate`). Reservation is a compare-and-swap UPDATE: two workers can't take one job on any database |
| `redis` | list + delayed/reserved sorted sets manipulated by one atomic Lua script |

## Workers

```bash
php naluz queue:work --queue=default --sleep=3          # long-running; SIGTERM/SIGINT finish the current job first
php naluz queue:work --once | --stop-when-empty | --max-jobs=500 | --memory=128
php naluz queue:failed      php naluz queue:retry 5|all      php naluz queue:flush
```

Run workers under a supervisor (systemd, supervisord) and restart them on deploy. A job whose worker crashed becomes
visible again after `queue.retry_after` seconds (keep it above your slowest job). Failed jobs (after `$tries`) are stored in
`failed_jobs` — also for the Redis driver.

## Schedule jobs

See [scheduler](scheduler.md).

## Security

- Payloads are **JSON encrypted with your `APP_KEY`** (XChaCha20-Poly1305), never PHP `serialize()`. Someone who can write
  to your database or Redis but lacks the key can neither read job data nor enqueue forged jobs; tampered payloads go straight
  to `failed_jobs` without executing.
- A decoded class must extend `Naluz\Queue\Job`, so a payload can't make the worker instantiate arbitrary classes.
- Don't put secrets you wouldn't want in the failed-jobs table into job properties; pass IDs and re-load.

## Tests

Use `queue.default = sync` for inline execution, or the `database` driver and call `Worker::runNextJob()`
(see `tests/Queue/QueueContract.php`, which runs the same contract against the database **and** Redis drivers).
