# Changelog

## Unreleased

### Added
- Compiled, auto-escaping template engine (`*.naluz.php`): `{{ }}` escapes, `{!! !!}` is raw; layouts, sections, stacks,
  includes, loops, `@csrf`, `@method`, `@json`, `@auth`/`@guest`.
- Queues: sync, database and Redis drivers; encrypted JSON payloads, retries with backoff, failed-job table,
  `queue:work/failed/retry/flush`.
- Mail: MIME builder, SMTP (STARTTLS/SSL, AUTH PLAIN/LOGIN), log and array transports, queued sending,
  header-injection protection.
- Scheduler: cron expressions, fluent frequencies, overlap protection, `schedule:run/list`.
- File storage: root-confined local disks and a safe upload helper (content-based type detection).
- Redis: dependency-free client, cache, session handler and queue.
- ORM: polymorphic (`morphOne/morphMany/morphTo`) and through (`hasManyThrough/hasOneThrough`) relations,
  lazy-loading guard (N+1 → exception in local/testing), model factories and seeders.
- Route caching (`route:cache`), `naluz new` project scaffolder, `migrate:fresh`, `db:seed`, `view:clear`,
  Composer package auto-discovery (`extra.naluz.providers`).

### Changed
- Real environment variables now take precedence over `.env`.

## 1.0.0
- Initial NaluzPHP release: container, router, PSR-15 pipeline, query builder, ORM, migrations, validation,
  sessions, auth (session + JWT), CSRF, security headers, CORS, rate limiting, CLI.
