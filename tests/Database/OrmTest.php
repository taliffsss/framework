<?php

declare(strict_types=1);

namespace Naluz\Tests\Database;

use App\Models\Post;
use App\Models\User;
use Naluz\Database\Orm\Model;
use Naluz\Database\Orm\ModelNotFoundException;
use Naluz\Database\Orm\Relations\BelongsToMany;
use Naluz\Tests\TestCase;

enum Status: string
{
    case Draft = 'draft';
    case Live = 'live';
}

final class Tag extends Model
{
    protected array $fillable = ['name'];

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'post_tag', 'tag_id', 'post_id');
    }
}

final class Setting extends Model
{
    protected array $fillable = ['key', 'options', 'status', 'secret', 'starts_at'];
    protected array $casts = ['options' => 'array', 'status' => Status::class, 'secret' => 'encrypted', 'starts_at' => 'datetime'];
    protected ?string $table = 'settings';
}

final class TaggedPost extends Model
{
    protected ?string $table = 'posts';

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'post_tag', 'post_id', 'tag_id');
    }
}

final class OrmTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $s = $this->db()->schema();
        $s->create('tags', function ($t) {
            $t->id();
            $t->string('name');
            $t->timestamps();
        });
        $s->create('post_tag', function ($t) {
            $t->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
            $t->foreignId('tag_id')->constrained('tags')->cascadeOnDelete();
        });
        $s->create('settings', function ($t) {
            $t->id();
            $t->string('key');
            $t->text('options')->nullable();
            $t->string('status')->nullable();
            $t->text('secret')->nullable();
            $t->timestamp('starts_at')->nullable();
            $t->timestamps();
        });
    }

    private function user(string $email = 'ann@example.com'): User
    {
        return User::create(['name' => 'Ann', 'email' => $email, 'password' => 'secret-pass']);
    }

    public function testCreateFindUpdateDelete(): void
    {
        $user = $this->user();
        $this->assertTrue($user->exists);
        $this->assertSame(1, $user->id);
        $this->assertNotNull($user->created_at);

        $found = User::find(1);
        $this->assertInstanceOf(User::class, $found);
        $this->assertSame('Ann', $found->name);

        $found->name = 'Annie';
        $this->assertTrue($found->isDirty('name'));
        $this->assertSame(['name' => 'Annie'], $found->getDirty());
        $found->save();
        $this->assertFalse($found->isDirty());
        $this->assertSame('Annie', User::find(1)->name);

        $this->assertTrue($found->update(['name' => 'Anna']));
        $this->assertSame('Anna', $found->fresh()->name);

        $this->assertTrue($found->delete());
        $this->assertNull(User::find(1));
        $this->assertSame(0, User::count());
    }

    public function testFindOrFailThrowsNotFoundHttpException(): void
    {
        $this->expectException(ModelNotFoundException::class);
        User::findOrFail(404);
    }

    public function testModelNotFoundIsAn404Response(): void
    {
        $r = $this->json('GET', '/api/posts/999');
        $this->assertSame(404, $r->getStatusCode());
    }

    public function testTableNameInference(): void
    {
        $this->assertSame('users', (new User())->getTable());
        $this->assertSame('posts', (new Post())->getTable());
    }

    public function testMassAssignmentIsBlockedByDefault(): void
    {
        $user = User::create(['name' => 'Eve', 'email' => 'eve@x.io', 'password' => 'pw', 'id' => 999, 'is_admin' => 1]);
        $this->assertNotSame(999, $user->id);
        $this->assertNull($user->getRawAttributes()['is_admin'] ?? null);

        $bare = new class (['anything' => 'x']) extends Model {
        };
        $this->assertSame([], $bare->getRawAttributes(), 'a model without $fillable accepts nothing');
    }

    public function testHiddenAndHashedPassword(): void
    {
        $user = $this->user();
        $this->assertArrayNotHasKey('password', $user->toArray());
        $this->assertStringNotContainsString('password', $user->toJson());
        $hash = User::find(1)->getRawAttributes()['password'];
        $this->assertNotSame('secret-pass', $hash);
        $this->assertTrue(password_verify('secret-pass', $hash));
    }

    public function testCastsAndEncryption(): void
    {
        $s = Setting::create(['key' => 'k', 'options' => ['a' => 1], 'status' => Status::Live, 'secret' => 'top-secret', 'starts_at' => new \DateTimeImmutable('2026-05-01 10:00:00')]);
        $fresh = Setting::find($s->id);
        $this->assertSame(['a' => 1], $fresh->options);
        $this->assertSame(Status::Live, $fresh->status);
        $this->assertSame('top-secret', $fresh->secret);
        $this->assertNotSame('top-secret', $fresh->getRawAttributes()['secret'], 'stored encrypted');
        $this->assertInstanceOf(\DateTimeInterface::class, $fresh->starts_at);
        $this->assertSame('live', $fresh->toArray()['status']);
    }

    public function testTimestamps(): void
    {
        $user = $this->user();
        $created = $user->created_at;
        $this->assertMatchesRegularExpression('/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/', $created);
        $user->forceFill(['updated_at' => '2000-01-01 00:00:00'])->save();
        $this->assertSame('2000-01-01 00:00:00', User::find(1)->updated_at);
    }

    public function testHasManyBelongsToAndEagerLoadingAvoidsNPlusOne(): void
    {
        $users = [$this->user('a@x.io'), $this->user('b@x.io'), $this->user('c@x.io')];
        foreach ($users as $u) {
            for ($i = 1; $i <= 3; $i++) {
                $u->posts()->create(['title' => "{$u->email} #{$i}", 'body' => 'b', 'published' => $i % 2 === 1]);
            }
        }

        Model::preventLazyLoading(false); // demonstrate the problem the guard exists to catch
        $lazy = $this->queries(function () {
            foreach (User::all() as $u) {
                $u->posts;
            }
        });
        $eager = $this->queries(function () use (&$loaded) {
            $loaded = User::with('posts')->get();
            foreach ($loaded as $u) {
                $this->assertCount(3, $u->posts);
            }
        });
        Model::preventLazyLoading(true);
        $this->assertCount(4, $lazy, '1 + N queries when lazy');
        $this->assertCount(2, $eager, 'exactly 2 queries when eager loaded');

        $post = Post::with('author')->first();
        $this->assertInstanceOf(User::class, $post->author);
        $this->assertSame('a@x.io', $post->author->email);

        $this->assertSame(2, $users[0]->posts()->where('published', true)->count());
        $this->assertCount(2, Post::published()->where('user_id', 1)->get());
    }

    public function testEagerLoadConstraintsAndNesting(): void
    {
        $u = $this->user();
        $u->posts()->create(['title' => 'old', 'body' => 'b']);
        $u->posts()->create(['title' => 'new', 'body' => 'b']);

        $loaded = User::with(['posts' => fn ($q) => $q->where('title', 'new')])->first();
        $this->assertSame(['new'], $loaded->posts->pluck('title')->all());

        $nested = Post::with('author.posts')->first();
        $this->assertCount(2, $nested->author->posts);
        $this->assertArrayHasKey('author', $nested->toArray());

        $user = User::find(1)->load('posts');
        $this->assertTrue($user->relationLoaded('posts'));
    }

    public function testBelongsToManyAttachDetachSyncAndEagerLoad(): void
    {
        $u = $this->user();
        $p1 = $u->posts()->create(['title' => 'p1', 'body' => 'b']);
        $p2 = $u->posts()->create(['title' => 'p2', 'body' => 'b']);
        $php = Tag::create(['name' => 'php']);
        $orm = Tag::create(['name' => 'orm']);
        $db = Tag::create(['name' => 'db']);

        $post = TaggedPost::find($p1->id);
        $post->tags()->attach([$php->id, $orm->id]);
        $post->tags()->attach($php->id); // duplicate ignored
        $this->assertSame(['php', 'orm'], $post->tags()->get()->pluck('name')->all());

        $result = $post->tags()->sync([$orm->id, $db]);
        $this->assertSame([$db->id], $result['attached']);
        $this->assertSame([$php->id], $result['detached']);
        $this->assertEqualsCanonicalizing(['orm', 'db'], TaggedPost::with('tags')->find($p1->id)->tags->pluck('name')->all());

        TaggedPost::find($p2->id)->tags()->attach($orm);
        $all = TaggedPost::with('tags')->orderBy('id')->get();
        $this->assertCount(2, $all[0]->tags);
        $this->assertSame(['orm'], $all[1]->tags->pluck('name')->all());
        $this->assertArrayNotHasKey('pivot_post_id', $all[0]->tags->first()->getRawAttributes());

        $this->assertSame(['p1', 'p2'], Tag::with('posts')->where('name', 'orm')->first()->posts->pluck('title')->all());
        $post->tags()->detach();
        $this->assertCount(0, $post->tags()->get());
    }

    public function testSoftDeletes(): void
    {
        $u = $this->user();
        $post = $u->posts()->create(['title' => 't', 'body' => 'b']);
        $post->delete();

        $this->assertNull(Post::find($post->id));
        $this->assertSame(0, Post::count());
        $this->assertNotNull(Post::withTrashed()->find($post->id));
        $this->assertSame(1, Post::onlyTrashed()->count());
        $this->assertTrue(Post::withTrashed()->find($post->id)->trashed());

        Post::withTrashed()->find($post->id)->restore();
        $this->assertNotNull(Post::find($post->id));

        Post::find($post->id)->forceDelete();
        $this->assertNull(Post::withTrashed()->find($post->id));
    }

    public function testBulkDeleteOnSoftDeleteModelIsSoft(): void
    {
        $u = $this->user();
        $u->posts()->create(['title' => 'a', 'body' => 'b']);
        Post::where('title', 'a')->delete();
        $this->assertSame(0, Post::count());
        $this->assertSame(1, Post::withTrashed()->count());
    }

    public function testFirstOrCreateUpdateOrCreateAndDestroy(): void
    {
        $a = User::firstOrCreate(['email' => 'z@x.io'], ['name' => 'Z', 'password' => 'p']);
        $b = User::firstOrCreate(['email' => 'z@x.io'], ['name' => 'Other', 'password' => 'p']);
        $this->assertSame($a->id, $b->id);
        $this->assertSame('Z', $b->name);

        $c = User::updateOrCreate(['email' => 'z@x.io'], ['name' => 'Zed']);
        $this->assertSame('Zed', User::find($a->id)->name);
        $this->assertSame(1, User::count());
        $this->assertSame(1, User::destroy($c->id));
    }

    public function testModelEvents(): void
    {
        $log = [];
        User::creating(function (User $u) use (&$log) { $log[] = 'creating'; });
        User::created(function (User $u) use (&$log) { $log[] = 'created'; });
        User::updating(function () use (&$log) { $log[] = 'updating'; });
        User::deleted(function () use (&$log) { $log[] = 'deleted'; });
        $u = $this->user();
        $u->update(['name' => 'N']);
        $u->delete();
        $this->assertSame(['creating', 'created', 'updating', 'deleted'], $log);

        User::creating(fn () => false);
        $blocked = new User(['name' => 'x', 'email' => 'x@x', 'password' => 'p']);
        $this->assertFalse($blocked->save());
        $this->assertFalse($blocked->exists);
    }

    public function testPaginateAndChunkAndCursorOnModels(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->user("u{$i}@x.io");
        }
        $page = User::query()->orderBy('id')->paginate(2, 3);
        $this->assertSame(5, $page->total);
        $this->assertInstanceOf(User::class, $page->items->first());
        $this->assertSame('u5@x.io', $page->items->first()->email);

        $n = 0;
        User::query()->chunk(2, function ($models) use (&$n) {
            $n += count($models);
        });
        $this->assertSame(5, $n);
        $this->assertCount(5, iterator_to_array(User::query()->cursor()));
    }

    public function testToArrayIncludesLoadedRelationsAndSerialisesAsJson(): void
    {
        $u = $this->user();
        $u->posts()->create(['title' => 't', 'body' => 'b']);
        $arr = json_decode(json_encode(User::with('posts')->first()), true);
        $this->assertSame('t', $arr['posts'][0]['title']);
        $this->assertArrayNotHasKey('password', $arr);
    }

    public function testQueryInjectionThroughOrmWhere(): void
    {
        $this->user();
        $this->assertCount(0, User::where('email', "' OR 1=1 --")->get());
        $this->expectException(\InvalidArgumentException::class);
        User::where('email; DROP TABLE users', 'x')->get();
    }

    public function testRefreshAndNoContainerGuard(): void
    {
        $u = $this->user();
        $this->db()->table('users')->where('id', 1)->update(['name' => 'Changed']);
        $this->assertSame('Changed', $u->refresh()->name);
    }
}
