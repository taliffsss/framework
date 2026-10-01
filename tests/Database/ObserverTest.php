<?php

declare(strict_types=1);

namespace Naluz\Tests\Database;

use App\Models\Post;
use App\Models\User;
use App\Services\Greeter;
use Naluz\Database\Orm\Attributes\ObservedBy;
use Naluz\Database\Orm\Model;
use Naluz\Tests\TestCase;

final class RecordingObserver
{
    /** @var list<string> */
    public static array $calls = [];

    public function saving(Model $m): void
    {
        self::$calls[] = 'saving';
    }

    public function creating(Model $m): void
    {
        self::$calls[] = 'creating';
    }

    public function created(Model $m): void
    {
        self::$calls[] = 'created';
    }

    public function updating(Model $m): void
    {
        self::$calls[] = 'updating';
    }

    public function updated(Model $m): void
    {
        self::$calls[] = 'updated';
    }

    public function saved(Model $m): void
    {
        self::$calls[] = 'saved';
    }

    public function deleting(Model $m): void
    {
        self::$calls[] = 'deleting';
    }

    public function deleted(Model $m): void
    {
        self::$calls[] = 'deleted';
    }

    public function notAnEvent(Model $m): void
    {
        self::$calls[] = 'WRONG';
    }

    private function updatedPrivate(): void
    {
    }
}

final class VetoObserver
{
    public function creating(Model $m): bool
    {
        return false;
    }
}

final class NeedsServiceObserver
{
    public static int $built = 0;

    public function __construct(public readonly Greeter $greeter)
    {
        self::$built++;
    }

    public function created(Model $m): void
    {
        $m->setAttribute('title', $this->greeter->greet('x'));
    }
}

#[ObservedBy(RecordingObserver::class)]
class ObservedPost extends Model
{
    protected ?string $table = 'posts';
    protected array $fillable = ['user_id', 'title', 'body'];
}

class ChildOfObservedPost extends ObservedPost
{
}

final class ObserverTest extends TestCase
{
    private int $n = 0;

    private function user(): User
    {
        return User::create(['name' => 'Ann', 'email' => 'ann' . ++$this->n . '@example.test', 'password' => 'secret-password']);
    }

    protected function setUp(): void
    {
        parent::setUp();
        RecordingObserver::$calls = [];
        NeedsServiceObserver::$built = 0;
    }

    public function testSampleUserObserverNormalisesEmailThroughTheAttribute(): void
    {
        $user = User::create(['name' => 'Bob', 'email' => '  Bob@Example.TEST ', 'password' => 'secret-password']);
        $this->assertSame('bob@example.test', $user->email);
        $this->assertSame('bob@example.test', User::findOrFail($user->getKey())->email);
    }

    public function testSampleProviderAttachedPostObserver(): void
    {
        $u = $this->user();
        $post = Post::create(['user_id' => $u->getKey(), 'title' => '  Spaced title  ', 'body' => 'x']);
        $this->assertSame('Spaced title', $post->title);

        $post->published = true;
        $post->body = '  ';
        $this->assertFalse($post->save(), 'the observer vetoed the update');
        $this->assertFalse((bool) Post::findOrFail($post->getKey())->published);
    }

    public function testEveryEventFiresInOrderAndOnlyForRealEvents(): void
    {
        $post = ObservedPost::create(['user_id' => $this->user()->getKey(), 'title' => 'a', 'body' => 'b']);
        $this->assertSame(['saving', 'creating', 'created', 'saved'], RecordingObserver::$calls);

        RecordingObserver::$calls = [];
        $post->title = 'changed';
        $post->save();
        $this->assertSame(['saving', 'updating', 'updated', 'saved'], RecordingObserver::$calls);

        RecordingObserver::$calls = [];
        $post->delete();
        $this->assertSame(['deleting', 'deleted'], RecordingObserver::$calls);
        $this->assertNotContains('WRONG', RecordingObserver::$calls);
    }

    public function testObserversAreInheritedAndRegisteredOnce(): void
    {
        ObservedPost::observe(RecordingObserver::class); // already attached by the attribute: must not double up
        ObservedPost::create(['user_id' => $this->user()->getKey(), 'title' => 'a', 'body' => 'b']);
        $this->assertSame(['saving', 'creating', 'created', 'saved'], RecordingObserver::$calls);

        RecordingObserver::$calls = [];
        ChildOfObservedPost::create(['user_id' => $this->user()->getKey(), 'title' => 'c', 'body' => 'd']);
        $this->assertSame(['saving', 'creating', 'created', 'saved'], RecordingObserver::$calls, 'a child model inherits the attribute');
    }

    public function testReturningFalseCancelsTheOperation(): void
    {
        $uid = $this->user()->getKey();
        ChildOfObservedPost::observe(VetoObserver::class);
        $post = new ChildOfObservedPost(['user_id' => $uid, 'title' => 'a', 'body' => 'b']);
        $this->assertFalse($post->save());
        $this->assertFalse($post->exists);
        $this->assertSame(0, (int) $this->db()->table('posts')->where('title', '=', 'a')->count());
    }

    public function testObserversAreBuiltLazilyThroughTheContainer(): void
    {
        $uid = $this->user()->getKey();
        Model::setContainer($this->app);
        ObservedPost::observe(NeedsServiceObserver::class);
        $this->assertSame(0, NeedsServiceObserver::$built, 'not built until an event fires');
        $post = ObservedPost::create(['user_id' => $uid, 'title' => 'a', 'body' => 'b']);
        $this->assertSame(1, NeedsServiceObserver::$built);
        $this->assertSame('Hello, x! Welcome to NaluzPHP.', $post->title, 'the container injected the Greeter');
        ObservedPost::create(['user_id' => $uid, 'title' => 'a', 'body' => 'b']);
        $this->assertSame(1, NeedsServiceObserver::$built, 'one instance per model class');
    }

    public function testObserveAcceptsInstancesAndRejectsUnknownClasses(): void
    {
        $uid = $this->user()->getKey();
        ChildOfObservedPost::flushEventListeners();
        ChildOfObservedPost::observe(new RecordingObserver());
        RecordingObserver::$calls = [];
        ChildOfObservedPost::create(['user_id' => $uid, 'title' => 'a', 'body' => 'b']);
        $this->assertContains('created', RecordingObserver::$calls);

        $this->expectException(\InvalidArgumentException::class);
        ChildOfObservedPost::observe('No\\Such\\Observer');
    }

    public function testSampleProvidersAreRegisteredAndBindTheGreeter(): void
    {
        $this->assertSame('Hello, Ann! Welcome to NaluzPHP.', $this->app->make(Greeter::class)->greet('Ann'));
        $this->assertSame($this->app->make(Greeter::class), $this->app->make(Greeter::class), 'singleton');
        $this->assertContains(\App\Providers\AppServiceProvider::class, (array) config('app.providers'));
        $this->assertContains(\App\Providers\ObserverServiceProvider::class, (array) config('app.providers'));
    }
}
