# Security

NaluzPHP aims for "secure unless you opt out". This page lists what is automatic, how to configure it, and what remains
your responsibility.

## Automatic

**SQL** — see [database.md](database.md#sql-injection-safety). Real prepared statements; identifiers, operators and
directions are validated; raw SQL is opt-in.

**Mass assignment** — a model accepts nothing until `$fillable` lists the fields. Validators' `validated()` /
`validate()` return only keys that have rules, so `Post::create($validator->validate())` can't be polluted by extra input.

**CSRF** — all `web` routes use `VerifyCsrfToken`: unsafe methods need `_token` (form field, `csrf_field()`) or an
`X-CSRF-TOKEN` header equal to the session token, and a present `Origin` header must match the host. Failure → `419`.
API routes are cookie-less, so they use bearer tokens instead (CSRF doesn't apply). **Do not put cookie-session
authentication on `api` routes.**

**Sessions** — IDs are 160-bit random hex; the cookie is `HttpOnly; SameSite=Lax` (+ `Secure` on HTTPS or when
`SESSION_SECURE=true`); IDs unknown to the server are replaced (no fixation); `Auth::login()` regenerates the ID;
data is stored as JSON.

**Headers** — `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`,
`Cross-Origin-Opener-Policy`, a restrictive `Content-Security-Policy`, and HSTS over HTTPS. Customise or disable in
`config/security.php → headers`. The default CSP forbids inline scripts/styles; loosen it deliberately for your app.

**Passwords** — `Naluz\Security\Hasher` (Argon2id; bcrypt if unavailable). The `hashed` cast hashes on assignment, and
`Auth::attempt()` rehashes when parameters change and spends hashing time even for unknown users.

**Encryption** — `Naluz\Security\Encrypter` (XChaCha20-Poly1305). Rotate by moving the old key into
`config/app.php → previous_keys`. Use the `encrypted` cast for sensitive columns (not searchable).

**JWT** — HS256 only, algorithm pinned, signature compared with `hash_equals`, `exp` required, `nbf`/`iss` validated.

```php
$jwt = app(Naluz\Security\Jwt::class);
$token = $jwt->encode(['sub' => $user->id], ttl: 900);       // issue
$router->get('/me', fn ($req) => User::find($req->getAttribute('auth.id')))->middleware('jwt');
```

For refresh tokens / revocation, store a token ID (`jti`) server-side; the framework keeps JWTs stateless on purpose.

**Rate limiting** — `->middleware('throttle:5,1')` (5 requests/minute per IP + path). The default api group allows 60/min.
Apply a tight limit to login and password-reset endpoints. The counter lives in the PSR-16 cache; with a shared
cache backend it works across servers. Note: behind a reverse proxy, `REMOTE_ADDR` is the proxy — configure your web
server to restore the real client IP (e.g. `mod_remoteip`, nginx `real_ip_module`) rather than trusting
`X-Forwarded-For` blindly.

**CORS** — off until `security.cors.allowed_origins` lists origins.

**Errors** — with `APP_DEBUG=false` clients only see generic messages; details go to `storage/logs/naluz-YYYY-MM-DD.log`
(newlines in log context are neutralised).

## Your responsibility

- Escape output: `e()` in views. Views are not auto-escaped (deliberately simple); never echo raw user input.
- Authorisation (who may edit *this* record). Authentication is provided; policies are up to your controllers.
- Allow-list sortable/filterable columns taken from user input.
- File uploads (none built in): validate type/size server-side, store outside `public/`, never trust client file names.
- Serve over HTTPS; keep `.env` outside the web root (default layout does: only `public/` is served); keep dependencies updated.
- Behind a proxy: set the real client IP, and only trust forwarded headers from your proxy.

## Reporting vulnerabilities

Please report privately to the maintainer (anthony.naluz15@gmail.com) rather than opening a public issue.
