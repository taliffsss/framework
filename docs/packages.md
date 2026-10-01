# Building packages & the ecosystem

A NaluzPHP package is an ordinary Composer package. To be picked up automatically after `composer require`, list your
service providers in its `composer.json`:

```json
{
  "name": "acme/blog",
  "require": { "naluz/naluzphp": "^1.0" },
  "autoload": { "psr-4": { "Acme\\Blog\\": "src/" } },
  "extra": { "naluz": { "providers": ["Acme\\Blog\\BlogServiceProvider"] } }
}
```

```php
final class BlogServiceProvider extends Naluz\Foundation\ServiceProvider
{
    public function register(): void { $this->app->singleton(Blog::class, fn ($c) => new Blog($c->make(Connection::class))); }
    public function boot(): void
    {
        $router = $this->app->make(Naluz\Routing\Router::class);
        $router->get('/blog', [BlogController::class, 'index']);
    }
}
```

Packages can ship middleware (any PSR-15 middleware works), console commands (extend `Naluz\Console\Command`),
migrations (point `Migrator` at your path), model factories, jobs and event listeners.

## Controlling discovery

- Opt a package out in your app's `composer.json`: `"extra": {"naluz": {"dont-discover": ["acme/blog"]}}`
  (or `['*']` for none), or set `app.dont_discover` in `config/app.php`.
- Only classes extending `ServiceProvider` are ever instantiated from package metadata; anything else is ignored.

## Community

Code can't create a community, but the groundwork is here: [CONTRIBUTING](../CONTRIBUTING.md),
[code of conduct](../CODE_OF_CONDUCT.md), [security policy](../SECURITY.md), issue/PR templates and a CHANGELOG.
Publish packages with the `naluz` keyword on Packagist so they're easy to find.
