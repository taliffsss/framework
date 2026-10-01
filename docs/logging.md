# Logging & error handling

## Channels

NaluzPHP implements **PSR-3** itself — no logging library needed. The application logger (`Naluz\Log\LogManager`, bound to
`Psr\Log\LoggerInterface`) sends records to a *channel*; channels live in `config/logging.php` and are chosen with `LOG_CHANNEL`.

```php
logger()->warning('Disk almost full');                              // default channel
logger()->channel('slack')->critical('Payment provider {p} down', ['p' => 'Stripe']);
logger()->stack(['daily', 'slack'])->error('Checkout failed', ['exception' => $e]);   // ad-hoc stack
logger('Order {id} shipped', ['id' => $order->id]);                 // info shortcut
```

| Driver | What it does | Options |
|---|---|---|
| `daily` (default) | `storage/logs/naluz-YYYY-MM-DD.log`, files older than `days` are deleted | `level`, `days`, `path`, `format` |
| `single` | one file, e.g. `storage/logs/naluz.log` | `level`, `file`, `path`, `format` |
| `stderr` / `stdout` | for containers, Kubernetes, systemd — the platform collects it | `level`, `format` |
| `errorlog` | PHP's own `error_log()` (web server / php-fpm) | `level` |
| `slack` | Slack incoming webhook | `url`, `level` (default `critical`), `username`, `emoji`, `include_trace` |
| `stack` | several channels at once; one failing member never blocks the others | `channels` |
| `null` | discard | — |
| `custom` | any PSR-3 logger (Monolog, a Sentry handler…) | `via` (class name or closure returning a `LoggerInterface`) |

`format` is `line` (default, human-readable) or `json` (one JSON object per line for Loki / Elastic / Datadog / CloudWatch).
Every channel has its own minimum `level` (`debug` … `emergency`).

### Typical setups

```
development              LOG_CHANNEL=daily
production on a VM       LOG_CHANNEL=production   (daily files + Slack for critical) and LOG_SLACK_WEBHOOK_URL=https://hooks.slack.com/…
containers / Kubernetes  LOG_CHANNEL=stderr       (set 'format' => 'json' in config/logging.php)
```

### Slack

Create an *Incoming Webhook* in Slack, put the URL in `LOG_SLACK_WEBHOOK_URL`, and add `slack` to a stack (or log to it directly).

- Defaults to `critical` and above — Slack is for things that need a human. Change with `LOG_SLACK_LEVEL`.
- Messages show app name + environment, the level (colour-coded), the interpolated message, and the exception class and
  `file:line` (basename only). **Stack traces are not sent** unless you set `include_trace` (only for private channels).
- User-controlled text is escaped (`& < >`), so a log line can't inject `<!channel>` pings or fake links.
- It never throws: if Slack is down or returns an error, the record falls back to PHP's `error_log()`.
- The webhook URL is a secret and is never written to any log; only `https://hooks.slack.com/…` URLs are accepted.
- Slack is called synchronously with a short timeout. For high volume, send to Slack from a queued job instead of per request.

### Your own channel

```php
// config/logging.php
'sentry' => ['driver' => 'custom', 'via' => fn (array $config) => new App\Logging\SentryLogger(env('SENTRY_DSN'))],
```

Any object implementing `Psr\Log\LoggerInterface` works as a channel.

## Where errors are caught

| Situation | What happens |
|---|---|
| Exception in a controller / middleware | `ExceptionHandler` renders a JSON or HTML response (details only if `APP_DEBUG=true`); **5xx are logged** with the exception |
| 4xx (`HttpException`, validation, 404) | rendered, **not** logged (they are normal) |
| PHP warning / notice | `ErrorHandler` converts it to an `ErrorException`, so it is handled like any other exception — never silently ignored (`@` and `error_reporting` are respected) |
| PHP deprecation | logged at `warning`, execution continues |
| Uncaught exception outside a request (CLI, queue worker, bootstrap) | logged at `critical`; the CLI prints a short message (full trace only in debug) |
| Fatal error (out of memory, parse error) | logged at `critical` from a shutdown handler |
| Failed queue job after its last retry | logged, stored in `failed_jobs`, `Job::failed()` called |
| Failed scheduled task | logged and reported by `schedule:run`; other tasks still run |

`public/index.php` (`Application::run()`) and the `naluz` CLI register the `ErrorHandler`.

## If logging itself fails

A log must never take the application down, and must never lose a message silently. If `storage/logs` is missing or
unwritable (read-only filesystem, disk full, bad permissions), `FileLogger` falls back to PHP's own `error_log()` — i.e.
the web server / php-fpm error log or stderr. `ErrorHandler` does the same if the PSR-3 logger *throws*.

So there is always at least one place the message lands. In containers, point the logger at stderr (as above) and let
the platform collect it.
