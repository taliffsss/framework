<?php

declare(strict_types=1);

namespace Naluz\Tests\Database;

use Naluz\Database\Orm\LazyLoadingViolationException;
use Naluz\Database\Orm\Model;
use Naluz\Database\Orm\Relations\HasManyThrough;
use Naluz\Database\Orm\Relations\HasOneThrough;
use Naluz\Database\Orm\Relations\MorphMany;
use Naluz\Database\Orm\Relations\MorphOne;
use Naluz\Database\Orm\Relations\MorphTo;
use Naluz\Tests\TestCase;

final class Country extends Model
{
    protected bool $timestamps = false;
    protected array $fillable = ['name'];

    public function posts(): HasManyThrough
    {
        return $this->hasManyThrough(Article::class, Author::class, 'country_id', 'author_id');
    }

    public function latestPost(): HasOneThrough
    {
        return $this->hasOneThrough(Article::class, Author::class, 'country_id', 'author_id')->orderBy('articles.id', 'desc');
    }
}

final class Author extends Model
{
    protected bool $timestamps = false;
    protected array $fillable = ['name', 'country_id'];
}

final class Article extends Model
{
    protected bool $timestamps = false;
    protected array $fillable = ['title', 'author_id'];

    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    public function cover(): MorphOne
    {
        return $this->morphOne(Image::class, 'imageable');
    }
}

final class Video extends Model
{
    protected bool $timestamps = false;
    protected array $fillable = ['title'];

    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }
}

final class Comment extends Model
{
    protected bool $timestamps = false;
    protected array $fillable = ['body'];

    public function commentable(): MorphTo
    {
        return $this->morphTo('commentable');
    }
}

final class Note extends Model
{
    protected bool $timestamps = false;
    protected array $fillable = ['body', 'author_id'];

    public function author(): \Naluz\Database\Orm\Relations\BelongsTo
    {
        return $this->belongsTo(Author::class);
    }
}

final class Image extends Model
{
    protected bool $timestamps = false;
    protected array $fillable = ['url'];
}

final class RelationsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $s = $this->db()->schema();
        $s->create('countries', fn ($t) => [$t->id(), $t->string('name')]);
        $s->create('authors', function ($t) {
            $t->id();
            $t->string('name');
            $t->integer('country_id');
        });
        $s->create('articles', function ($t) {
            $t->id();
            $t->string('title');
            $t->integer('author_id');
        });
        $s->create('videos', fn ($t) => [$t->id(), $t->string('title')]);
        $s->create('comments', function ($t) {
            $t->id();
            $t->string('body');
            $t->string('commentable_type')->nullable();
            $t->integer('commentable_id')->nullable();
        });
        $s->create('images', function ($t) {
            $t->id();
            $t->string('url');
            $t->string('imageable_type');
            $t->integer('imageable_id');
        });
        $s->create('notes', function ($t) {
            $t->id();
            $t->string('body');
            $t->integer('author_id')->nullable();
        });
        Model::morphMap([], false);
    }

    public function testBelongsToWithNullableForeignKeyEagerLoads(): void
    {
        $a = Author::create(['name' => 'A', 'country_id' => 1]);
        Note::create(['body' => 'owned', 'author_id' => $a->id]);
        Note::create(['body' => 'orphan', 'author_id' => null]);
        $notes = Note::with('author')->orderBy('id')->get();
        $this->assertSame('A', $notes[0]->author->name);
        $this->assertNull($notes[1]->author);
    }

    protected function tearDown(): void
    {
        Model::morphMap([], false);
        parent::tearDown();
    }

    // ---------------------------------------------------------------- lazy loading guard

    public function testLazyLoadingGuardTurnsNPlusOneIntoAnError(): void
    {
        $c = Country::create(['name' => 'PH']);
        $a = Author::create(['name' => 'A', 'country_id' => $c->id]);
        Article::create(['title' => 't', 'author_id' => $a->id]);

        $loaded = Country::first();
        try {
            $loaded->posts;
            $this->fail('lazy loading should have thrown');
        } catch (LazyLoadingViolationException $e) {
            $this->assertStringContainsString("with('posts')", $e->getMessage());
        }
        $this->assertCount(1, Country::with('posts')->first()->posts, 'eager loading is fine');
        $this->assertCount(1, Country::first()->load('posts')->posts, 'explicit load() is fine');
        $this->assertCount(1, $loaded->posts()->get(), 'calling the relation as a query is fine');
    }

    public function testLazyLoadingGuardIsOnInTestingAndCanBeSwitchedOff(): void
    {
        $this->assertTrue((function () {
            try {
                Country::create(['name' => 'x']);
                Country::first()->posts;
            } catch (LazyLoadingViolationException) {
                return true;
            }
            return false;
        })(), 'on by default when APP_ENV=testing');

        Model::preventLazyLoading(false);
        $this->assertCount(0, Country::first()->posts);
    }

    public function testRecentlyCreatedModelsAreExempt(): void
    {
        $c = Country::create(['name' => 'PH']);
        $this->assertCount(0, $c->posts);
    }

    // ---------------------------------------------------------------- has-many-through

    public function testHasManyThroughLazyAndEager(): void
    {
        $ph = Country::create(['name' => 'PH']);
        $us = Country::create(['name' => 'US']);
        $a1 = Author::create(['name' => 'A1', 'country_id' => $ph->id]);
        $a2 = Author::create(['name' => 'A2', 'country_id' => $ph->id]);
        $a3 = Author::create(['name' => 'A3', 'country_id' => $us->id]);
        foreach ([[$a1, 'one'], [$a2, 'two'], [$a3, 'three'], [$a1, 'four']] as [$a, $title]) {
            Article::create(['title' => $title, 'author_id' => $a->id]);
        }

        Model::preventLazyLoading(false);
        $this->assertEqualsCanonicalizing(['one', 'two', 'four'], Country::find($ph->id)->posts->pluck('title')->all());

        $queries = $this->queries(function () use (&$countries) {
            $countries = Country::with('posts')->orderBy('id')->get();
        });
        $this->assertCount(2, $queries);
        $this->assertCount(3, $countries[0]->posts);
        $this->assertSame(['three'], $countries[1]->posts->pluck('title')->all());
        $this->assertArrayNotHasKey('naluz_through_key', $countries[0]->posts->first()->getRawAttributes());
        $this->assertSame(3, $ph->posts()->count());
    }

    public function testHasOneThrough(): void
    {
        $ph = Country::create(['name' => 'PH']);
        $a = Author::create(['name' => 'A', 'country_id' => $ph->id]);
        Article::create(['title' => 'old', 'author_id' => $a->id]);
        Article::create(['title' => 'new', 'author_id' => $a->id]);
        Model::preventLazyLoading(false);
        $this->assertSame('new', Country::find($ph->id)->latestPost->title);
        $this->assertSame('new', Country::with('latestPost')->first()->latestPost->title);
        $empty = Country::create(['name' => 'none']);
        $this->assertNull(Country::with('latestPost')->find($empty->id)->latestPost);
    }

    // ---------------------------------------------------------------- polymorphic

    public function testMorphManyAndMorphOne(): void
    {
        $post = Article::create(['title' => 'p', 'author_id' => 1]);
        $video = Video::create(['title' => 'v']);
        $post->comments()->create(['body' => 'on post 1']);
        $post->comments()->create(['body' => 'on post 2']);
        $video->comments()->create(['body' => 'on video']);
        $post->cover()->create(['url' => '/a.png']);

        $this->assertSame(Article::class, $this->db()->table('comments')->where('body', 'on post 1')->value('commentable_type'));
        Model::preventLazyLoading(false);
        $this->assertSame(['on post 1', 'on post 2'], Article::find($post->id)->comments->pluck('body')->all());
        $this->assertSame(['on video'], Video::find($video->id)->comments->pluck('body')->all());
        $this->assertSame('/a.png', Article::find($post->id)->cover->url);
        $this->assertCount(2, Article::with('comments')->first()->comments);
    }

    public function testMorphToLazyAndEagerAcrossTypes(): void
    {
        $post = Article::create(['title' => 'a post', 'author_id' => 1]);
        $video = Video::create(['title' => 'a video']);
        $post->comments()->create(['body' => 'c1']);
        $video->comments()->create(['body' => 'c2']);
        $post->comments()->create(['body' => 'c3']);
        Comment::create(['body' => 'orphan']);

        Model::preventLazyLoading(false);
        $this->assertInstanceOf(Video::class, Comment::where('body', 'c2')->first()->commentable);
        $this->assertNull(Comment::where('body', 'orphan')->first()->commentable);

        $queries = $this->queries(function () use (&$comments) {
            $comments = Comment::with('commentable')->orderBy('id')->get();
        });
        $this->assertCount(3, $queries, '1 for comments + 1 per distinct type, regardless of row count');
        $this->assertSame(['a post', 'a video', 'a post', null], $comments->map(fn ($c) => $c->commentable?->title)->values()->all());
    }

    public function testMorphMapUsesAliasesAndRejectsUnknownTypes(): void
    {
        Model::morphMap(['article' => Article::class, 'video' => Video::class]);
        $post = Article::create(['title' => 'p', 'author_id' => 1]);
        $post->comments()->create(['body' => 'x']);
        $this->assertSame('article', $this->db()->table('comments')->value('commentable_type'));
        Model::preventLazyLoading(false);
        $this->assertSame('p', Comment::first()->commentable->title);

        // tampered column: not in the map → refused, no class is instantiated
        $this->db()->table('comments')->update(['commentable_type' => 'stdClass']);
        $this->expectException(\InvalidArgumentException::class);
        Comment::first()->commentable;
    }

    public function testMorphToRefusesNonModelClassesEvenWithoutAMap(): void
    {
        $this->db()->table('comments')->insert(['body' => 'evil', 'commentable_type' => \ArrayObject::class, 'commentable_id' => 1]);
        Model::preventLazyLoading(false);
        $this->expectException(\InvalidArgumentException::class);
        Comment::first()->commentable;
    }
}
