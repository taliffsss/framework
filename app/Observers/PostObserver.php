<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Post;

/** Sample observer attached from ObserverServiceProvider. */
final class PostObserver
{
    /** Runs before every INSERT and UPDATE. */
    public function saving(Post $post): void
    {
        $post->title = trim((string) $post->title);
    }

    /** Refuse to publish a post without a body: returning false cancels the save. */
    public function updating(Post $post): ?bool
    {
        return $post->published && trim((string) $post->body) === '' ? false : null;
    }
}
