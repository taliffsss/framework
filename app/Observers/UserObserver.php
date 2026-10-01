<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\User;

/** Sample observer: public methods named after model events are called with the model. */
final class UserObserver
{
    /** Runs before INSERT. Returning false would cancel the save. */
    public function creating(User $user): void
    {
        $user->email = strtolower(trim((string) $user->email));
    }

    public function created(User $user): void
    {
        logger('User created', ['id' => $user->getKey()]); // never log passwords or other secrets
    }

    public function deleted(User $user): void
    {
        logger('User deleted', ['id' => $user->getKey()]);
    }
}
