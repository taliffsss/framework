# Service providers & model observers

## Service providers

A provider is where you wire things into the container. Extend `Naluz\Foundation\ServiceProvider`:

```php
final class AppServiceProvider extends ServiceProvider
{
    public function register(): void      // bind services; do not resolve other services here
    {
        $this->app->singleton(Greeter::class, fn ($c) => new Greeter('NaluzPHP'));
    }

    public function boot(): void          // runs after every provider registered
    {
        Model::morphMap(['user' => User::class]);
    }
}
```

Register your providers in `config/app.php`:

```php
'providers' => [
    App\Providers\AppServiceProvider::class,
    App\Providers\ObserverServiceProvider::class,
],
```

The skeleton ships two samples in `app/Providers/`. Generate your own with `php naluz make:provider PaymentServiceProvider`.
Composer packages can register providers automatically through `extra.naluz.providers` (see [packages](packages.md)).

## Model observers

An observer groups the listeners for one model. Any public method named after a model event is called with the model:
`saving`, `saved`, `creating`, `created`, `updating`, `updated`, `deleting`, `deleted`.

```php
final class UserObserver
{
    public function creating(User $user): void
    {
        $user->email = strtolower(trim($user->email));
    }

    public function updating(User $user): ?bool
    {
        return $user->isDirty('email') && !$user->email_verified ? false : null;   // false cancels the save
    }
}
```

Attach it one of two ways:

```php
// 1. declaratively, on the model (inherited by child models)
#[ObservedBy(UserObserver::class)]
class User extends Model {}

// 2. from a provider
User::observe(UserObserver::class);        // a class name or an object
```

* Class names are built **lazily** on the first event, through the container when it is available, so constructor
  dependencies are injected. One instance is reused per model class.
* Attaching the same observer twice is ignored.
* Order of events on create: `saving → creating → created → saved`; on update: `saving → updating → updated → saved`;
  on delete: `deleting → deleted` (soft deletes also run the update events).
* Returning `false` from a `saving`/`creating`/`updating`/`deleting` method cancels the operation.
* `Model::flushEventListeners()` removes every listener and observer (useful in tests).
* Observers run on model instances only. Bulk query-builder writes (`->update()`, `->delete()` on a query) do not fire
  model events, the same as closures registered with `User::created(fn …)`.

Generate one with `php naluz make:observer UserObserver`.
