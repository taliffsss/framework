<?php

declare(strict_types=1);

namespace Naluz\Tests\Database;

use App\Models\Post;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Naluz\Database\Seeder;
use Naluz\Support\Fake;
use Naluz\Tests\TestCase;

final class FactoryTest extends TestCase
{
    public function testMakeDoesNotPersistAndCreateDoes(): void
    {
        $made = User::factory()->make();
        $this->assertFalse($made->exists);
        $this->assertSame(0, User::count());

        $created = User::factory()->create();
        $this->assertTrue($created->exists);
        $this->assertSame(1, User::count());
        $this->assertTrue(password_verify('password', $created->getRawAttributes()['password']));
    }

    public function testCountStateAndOverrides(): void
    {
        $users = User::factory(3)->create();
        $this->assertCount(3, $users);
        $this->assertCount(3, array_unique($users->pluck('email')->all()), 'emails are unique');

        $u = User::factory()->state(['name' => 'Fixed'])->create(['email' => 'f@x.io']);
        $this->assertSame('Fixed', $u->name);
        $this->assertSame('f@x.io', $u->email);

        $s = User::factory()->state(fn (array $a) => ['name' => strtoupper($a['name'])])->make();
        $this->assertSame(strtoupper($s->name), $s->name);
    }

    public function testFactoriesBypassFillableBecauseTheyAreTrustedCode(): void
    {
        $post = Post::factory()->create(['deleted_at' => null, 'id' => 4242]);
        $this->assertSame(4242, $post->id);
    }

    public function testNestedFactoriesAreCreatedAndLinked(): void
    {
        $post = Post::factory()->published()->create();
        $this->assertSame(1, User::count());
        $this->assertSame($post->user_id, User::first()->id);
        $this->assertTrue($post->published);
        $this->assertCount(2, Post::factory(2)->create(['user_id' => $post->user_id]));
        $this->assertSame(1, User::count(), 'explicit foreign key suppresses the nested factory');
    }

    public function testAfterCreatingAndRaw(): void
    {
        $seen = [];
        User::factory()->afterCreating(function ($u) use (&$seen) { $seen[] = $u->id; })->count(2)->create();
        $this->assertSame([1, 2], $seen);
        $before = User::count();
        $raw = Post::factory()->raw(['user_id' => 7]);
        $this->assertSame(7, $raw['user_id']);
        $this->assertSame($before, User::count(), 'raw() never touches the database');
    }

    public function testMissingFactoryExplainsWhatToDo(): void
    {
        $model = new class extends \Naluz\Database\Orm\Model {
            use \Naluz\Database\Orm\HasFactory;
        };
        $this->expectException(\LogicException::class);
        $model::factory();
    }

    public function testSeedAndSeederCall(): void
    {
        $this->app->make(DatabaseSeeder::class)->run();
        $this->assertSame(3, User::count());
        $this->assertSame(15, Post::count());

        $child = new class ($this->app) extends Seeder {
            public function run(): void
            {
                $this->call(\Database\Seeders\DatabaseSeeder::class);
            }
        };
        $child->run();
        $this->assertSame(6, User::count());

        $this->expectException(\InvalidArgumentException::class);
        $child->call(\stdClass::class);
    }

    public function testFakeIsReproducibleWhenSeeded(): void
    {
        Fake::seed(1234);
        $a = [Fake::instance()->name(), Fake::instance()->sentence(), Fake::instance()->uuid(), Fake::instance()->numberBetween(1, 1000)];
        Fake::seed(1234);
        $b = [Fake::instance()->name(), Fake::instance()->sentence(), Fake::instance()->uuid(), Fake::instance()->numberBetween(1, 1000)];
        $this->assertSame($a, $b);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $a[2]);
        Fake::seed(null);
    }
}
