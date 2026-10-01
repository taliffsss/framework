<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Post;
use App\Observers\PostObserver;
use Naluz\Foundation\ServiceProvider;

/**
 * Sample provider that attaches model observers. The other way is declarative: put
 * #[ObservedBy(UserObserver::class)] on the model (see App\Models\User).
 */
final class ObserverServiceProvider extends ServiceProvider
{
    /** @var array<class-string<\Naluz\Database\Orm\Model>,class-string> model => observer */
    protected array $observers = [
        Post::class => PostObserver::class,
    ];

    public function boot(): void
    {
        foreach ($this->observers as $model => $observer) {
            $model::observe($observer);
        }
    }
}
