# Routing, HTTP & views

NaluzPHP speaks PSR-7 / PSR-15 natively: requests are plain `Psr\Http\Message\ServerRequestInterface` objects and
middleware are standard PSR-15 `MiddlewareInterface` implementations, so third-party middleware just works.

## Routes

`routes/web.php` is wrapped in the `web` group (sessions + CSRF). `routes/api.php` is wrapped in the `api` group,
prefixed with `/api`, named `api.*`, stateless (no cookies, no CSRF) and rate limited. Both are defined in
`config/app.php` (`middleware_groups`).

```php
$router->get('/', fn () => view('home'));
$router->post('/contact', [ContactController::class, 'send']);
$router->match(['GET', 'POST'], '/multi', $handler);
$router->any('/ping', $handler);

// parameters
$router->get('/users/{id}', …)->where('id', '[0-9]+');
$router->get('/posts/{slug:[a-z0-9-]+}', …);       // inline pattern
$router->get('/archive/{year?}', fn (?int $year = null) => …);   // optional

// groups
$router->prefix('admin')->name('admin.')->middleware('auth')->group(function ($router) { … });

// REST resources: index, create, store, show, edit, update (PUT+PATCH), destroy
$router->resource('photos', PhotoController::class, only: ['index', 'show']);
$router->apiResource('photos', PhotoController::class);   // no create/edit

// URL generation
route('users.show', ['id' => 5]);          // /users/5
route('users.show', ['id' => 5, 'q' => 1]) // /users/5?q=1
```

Unknown path → 404. Known path with another method → 405 with an `Allow` header.

HTML forms can send `PUT/PATCH/DELETE` with `<input type="hidden" name="_method" value="DELETE">`.

## Controllers

Any callable or `[Class::class, 'method']`. Dependencies are injected by type, route parameters by name:

```php
public function show(ServerRequestInterface $request, UserRepository $users, int $id): User
```

Return values: `ResponseInterface` as-is · `array`, `JsonSerializable` (models, collections, paginators) → JSON ·
`string` → HTML · `null` → `204 No Content`.

### Reading input

```php
Request::input($request)            // query + parsed body (form or JSON; malformed JSON → 400)
Request::body($request)
Request::bearerToken($request)
Request::ip($request)
$request->getAttribute('route')     // the matched Naluz\Routing\Route
```

### Responses

```php
json($data, 201)  ·  response('text')  ·  view('name', [...])  ·  redirect('/to')  ·  Response::noContent()
```

## Middleware

```bash
php naluz make:middleware EnsureAdmin
```

Register an alias in `config/app.php → middleware_aliases` and use it on routes: `->middleware('admin')`.
Parameters: `->middleware('throttle:10,1')` is passed to the constructor as `array $parameters = []`.

Built-in: `SecurityHeaders`, `Cors`, `MethodOverride` (global) · `StartSession`, `VerifyCsrfToken` (web) ·
`Throttle`, `Authenticate`, `AuthenticateJwt` (aliases `throttle`, `auth`, `jwt`).

## Views

Views are `resources/views/<name>.naluz.php`, compiled to cached PHP by the built-in template engine. Output is
**escaped by default**:

```
@extends('layouts/app')
@section('title', $post->title)
@section('content')
  <h1>{{ $post->title }}</h1>
  @foreach ($post->comments as $c) <p>{{ $c->body }}</p> @endforeach
  <form method="POST" action="/posts">@csrf @method('PUT') …</form>
@endsection
```

Full reference: [templates.md](templates.md). Plain `.php` views with `$this->e()` still work.

Validation failures from browsers redirect back with flashed `errors` and `old` input (passwords excluded): read with
`app(Naluz\Session\Store::class)->get('errors')`. Custom error pages: `resources/views/errors/404.naluz.php` (any status code).

## Sessions, cache, logging, events

```php
$session = app(Naluz\Session\Store::class);   $session->put('k', 'v'); $session->flash('notice', 'Saved');
$cache   = app(Psr\SimpleCache\CacheInterface::class);   $cache->set('k', $v, 300);
$log     = app(Psr\Log\LoggerInterface::class);          $log->warning('Login failed for {email}', ['email' => $e]);
$events  = app(Naluz\Events\Dispatcher::class);          $events->listen(UserRegistered::class, fn ($e) => …); $events->dispatch(new UserRegistered($u));
```
