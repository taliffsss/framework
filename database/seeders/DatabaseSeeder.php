<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Post;
use App\Models\User;
use Naluz\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::factory(3)->create();
        foreach ($users as $user) {
            Post::factory(5)->create(['user_id' => $user->id]);
        }
    }
}
