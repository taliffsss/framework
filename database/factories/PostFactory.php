<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Post;
use Naluz\Database\Factory;

/** @extends Factory<Post> */
final class PostFactory extends Factory
{
    protected string $model = Post::class;

    public function definition(): array
    {
        return [
            'user_id' => UserFactory::new(),
            'title' => ucfirst($this->fake()->words(4)),
            'body' => $this->fake()->paragraph(),
            'published' => $this->fake()->boolean(70),
        ];
    }

    public function published(): static
    {
        return $this->state(['published' => true]);
    }
}
