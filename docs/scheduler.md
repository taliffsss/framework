# Scheduler

Add **one** cron entry on the server:

```
* * * * * cd /path/to/app && php naluz schedule:run >> /dev/null 2>&1
```

and define every task in `routes/console.php`:

```php
return function (Schedule $schedule): void {
    $schedule->command('queue:work --stop-when-empty')->everyMinute()->withoutOverlapping();
    $schedule->job(new PruneOldRecords())->dailyAt('03:00');
    $schedule->call(fn (Psr\SimpleCache\CacheInterface $cache) => $cache->clear())->hourly()->when(fn () => date('N') < 6);
    $schedule->command('db:seed --class=Database\\Seeders\\Refresh --force')->cron('0 4 * * 1');
};
```

| Frequency | |
|---|---|
| `everyMinute() everyFiveMinutes() everyTenMinutes() everyFifteenMinutes() everyThirtyMinutes()` | |
| `hourly() hourlyAt(15) daily() dailyAt('13:30') weekly() weeklyOn(1, '08:00') monthly() monthlyOn(15, '02:00')` | |
| `weekdays()` · `cron('*/10 9-17 * * mon-fri')` | full 5-field cron: lists, ranges, steps, names, `@daily`-style macros |

Constraints: `->when(fn)`, `->skip(fn)`, `->withoutOverlapping($minutes = 60)` (atomic lock in the cache; released even when
the task throws; the TTL is a safety net for crashed processes). With several servers use a shared cache (Redis) so
the lock is shared too.

`php naluz schedule:list` shows each task's next run. A failing task is logged and reported, but never stops the others.
Times use `app.timezone`.

Commands scheduled with `command()` are naluz CLI commands (not arbitrary shell strings — there is no shell injection
surface). Use `call()` for anything else.
