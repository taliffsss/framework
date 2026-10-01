<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Naluz\Database\Factory;

/** @extends Factory<User> */
final class UserFactory extends Factory
{
    protected string $model = User::class;

    public function definition(): array
    {
        return [
            'name' => $this->fake()->name(),
            'email' => $this->fake()->email(),
            'password' => 'password', // hashed by the model cast
        ];
    }
}
