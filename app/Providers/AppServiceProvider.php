<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Post;
use App\Models\User;
use App\Services\Greeter;
use Naluz\Config\Repository;
use Naluz\Database\Orm\Model;
use Naluz\Foundation\ServiceProvider;

/**
 * Sample application service provider (register it in config/app.php under 'providers').
 *
 * register() only binds things into the container; boot() runs after every provider has registered, so it may
 * resolve other services.
 */
final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // A shared instance, built on first use.
        $this->app->singleton(Greeter::class, fn ($c) => new Greeter((string) $c->make(Repository::class)->get('app.name', 'NaluzPHP')));
    }

    public function boot(): void
    {
        // Short names for polymorphic relations instead of class names in the database.
        Model::morphMap(['user' => User::class, 'post' => Post::class]);
    }
}
