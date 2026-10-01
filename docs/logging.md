# Logging & error handling

## What gets logged, and where

NaluzPHP implements **PSR-3** itself (`Naluz\Log\FileLogger`) — you don't need to install a logging library. It is bound to
`Psr\Log\LoggerInterface`, writes `storage/logs/naluz-YYYY-MM-DD.log`, honours a minimum level (`LOG_LEVEL`), interpolates
`{placeholders}` and neutralises newlines so user input can't forge log lines.

```php
logger('Order {id} shipped', ['id' => $order->id]);          // info
app(Psr\Log\LoggerInterface::class)->error('Payment failed: {m}', ['m' => $e->getMessage(), 'exception' => $e]);
```

To use Monolog (or anything PSR-3), bind it in a service provider — everything in the framework logs through the interface:

```php
$this->app->singleton(Psr\Log\LoggerInterface::class, fn () => new Monolog\Logger('app', [new StreamHandler('php://stderr')]));
```

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
