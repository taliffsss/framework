# Database

Three layers, usable independently:

1. `Connection` – PDO wrapper (prepared statements, transactions, listeners)
2. **Query builder** – `Naluz\Database\Query\Builder`, returns arrays
3. **ORM** – `Naluz\Database\Orm\Model`, returns models; built on top of (2)

```php
$manager = app(Naluz\Database\DatabaseManager::class);
$db = $manager->connection();           // default
$db->table('users')->where('active', true)->get();   // query builder, no models involved
User::where('active', true)->get();                  // ORM
```

## Query builder

```php
$db->table('users')
   ->select('id', 'name')->distinct()
   ->where('age', '>', 18)->orWhere('vip', true)
   ->where(fn ($q) => $q->where('a', 1)->orWhere('b', 2))      // grouped
   ->whereIn('id', [1, 2, 3])->whereNotIn(...)->whereNull('deleted_at')->whereBetween('age', [18, 65])
   ->whereExists(fn ($q) => $q->from('orders')->whereColumn('orders.user_id', '=', 'users.id'))
   ->whereIn('id', $db->table('orders')->select('user_id'))     // subquery
   ->join('profiles', 'users.id', '=', 'profiles.user_id')      // leftJoin, rightJoin, crossJoin
   ->groupBy('role')->having('n', '>', 1)
   ->orderBy('name', 'desc')->latest()->limit(10)->offset(20)
   ->when($term !== '', fn ($q) => $q->where('name', 'like', "%{$term}%"))
   ->get();
```

| Read | Write |
|---|---|
| `get() first() find($id) value($col) pluck($col, $key) exists()` | `insert() insertGetId() upsert() update() increment() decrement() delete() truncate()` |
| `count() sum() avg() min() max()` | `insert([[…], […]])` bulk, `insert($row, ignore: true)` |
| `paginate($perPage, $page)` → `Paginator` (JSON: `{data, meta}`) | `transaction(fn)` on the connection (nested = savepoints) |
| `chunk($size, fn)` · `cursor()` (generator, constant memory) | `toSql()` / `getBindings()` for debugging |

### SQL injection safety

- Every value is a **bound parameter**, never concatenated.
- Identifiers (`where($column…)`, `orderBy`, `select`, joins, table names) must be plain `name`, `table.name`,
  `table.*` or `x as y`. Anything else throws `InvalidArgumentException`, so passing user input as a column name can't
  inject — it fails loudly. **Still allow-list sortable columns** if you accept them from users.
- Operators and sort directions are allow-listed.
- Raw SQL requires an explicit opt-in: `selectRaw()`, `whereRaw($sql, $bindings)`, `orderByRaw()`, `Connection::raw()`.
  Put user input only in `$bindings`.

```php
$sortable = ['name', 'created_at'];
$col = in_array($req['sort'] ?? '', $sortable, true) ? $req['sort'] : 'created_at';
User::orderBy($col, ($req['dir'] ?? '') === 'asc' ? 'asc' : 'desc')->get();
```

## Migrations

```bash
php naluz make:migration create_orders_table
php naluz migrate | migrate:rollback --step=2 | migrate:status
```

```php
return new class extends Migration {
    public function up(Schema $schema): void
    {
        $schema->create('orders', function ($t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->string('number', 32)->unique();
            $t->decimal('total', 10, 2)->default(0);
            $t->boolean('paid')->default(false);
            $t->json('meta')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->index(['user_id', 'paid']);
        });
    }
    public function down(Schema $schema): void { $schema->dropIfExists('orders'); }
};
```

Column types: `id string text integer bigInteger boolean decimal float json date timestamp foreignId`.
Modifiers: `nullable() default() unsigned() unique() index() useCurrent() constrained() cascadeOnDelete()`.
`Schema::table()` adds columns / drops columns / adds indexes to existing tables. Migrations run inside a transaction
on PostgreSQL and SQLite (MySQL implicitly commits DDL).

## ORM

```php
class Post extends Model
{
    use SoftDeletes;                                   // optional

    protected ?string $table = 'posts';                // default: snake_case plural of the class name
    protected string $primaryKey = 'id';
    protected bool $timestamps = true;                 // created_at / updated_at
    protected array $fillable = ['title', 'body'];     // mass-assignment allow-list (default: nothing)
    protected array $hidden = ['internal_notes'];      // never serialised
    protected array $appends = ['excerpt'];            // accessor-backed extra fields
    protected array $casts = ['published' => 'bool', 'meta' => 'array', 'status' => Status::class, 'api_key' => 'encrypted'];

    public function getExcerptAttribute(): string { return mb_substr($this->body, 0, 80); }   // accessor
    public function setTitleAttribute(string $v): void { $this->setRawAttribute('title', trim($v)); }   // mutator
    public function scopePublished(Builder $q): void { $q->where('published', true); }
}
```

### CRUD

```php
$post = Post::create([...]);            // only $fillable keys are used
$post = Post::find(1);  Post::findOrFail(1);   // ModelNotFoundException → HTTP 404
Post::where('views', '>', 100)->orderBy('id')->get();      // Collection of models
$post->title = 'New'; $post->isDirty('title'); $post->save();
$post->update(['title' => 'x']);  $post->delete();  Post::destroy([1, 2]);
Post::firstOrCreate(['slug' => 'a'], ['title' => 'A']);   Post::updateOrCreate([...], [...]);
$post->forceFill([...]);                // bypasses $fillable — never feed it raw request data
$post->fresh(); $post->refresh();
```

### Relationships

```php
$user->hasMany(Post::class, 'user_id');        $user->hasOne(Profile::class);
$post->belongsTo(User::class, 'user_id');
$post->belongsToMany(Tag::class, 'post_tag', 'post_id', 'tag_id');   // table + keys optional

$user->posts;                              // lazy loaded property
$user->posts()->where('published', true)->get();   // relation as query
$user->posts()->create([...]);             // hasMany
$post->tags()->attach([1, 2]); ->detach(1); ->sync([2, 3]);
$comment->post()->associate($post);        // belongsTo

User::with('posts.comments', ['posts' => fn ($q) => $q->latest()])->get();   // eager loading
$user->load('posts');
```

Eager loading uses one query per relation (`WHERE fk IN (…)`), eliminating N+1 queries — the test suite asserts it.

#### Polymorphic relations

One table of comments for many owner types:

```php
// comments: id, body, commentable_type, commentable_id
class Post extends Model    { public function comments(): MorphMany { return $this->morphMany(Comment::class, 'commentable'); } }
class Comment extends Model { public function commentable(): MorphTo { return $this->morphTo('commentable'); } }

Model::morphMap(['post' => Post::class, 'video' => Video::class]);   // store aliases, not class names (recommended)

$post->comments()->create(['body' => 'Nice']);
Comment::with('commentable')->get();     // 1 query for comments + 1 per distinct type, however many rows
```

Also `morphOne`. The `_type` column is data, so it is **never trusted**: it must resolve (through the morph map when you
define one) to a `Model` subclass, otherwise `InvalidArgumentException` — a tampered row cannot instantiate arbitrary classes.
(`morphToMany` / nested eager loading through a `morphTo` are not implemented.)

#### Has-many-through

```php
// Country → users → posts
public function posts(): HasManyThrough { return $this->hasManyThrough(Post::class, User::class, 'country_id', 'user_id'); }
public function latestPost(): HasOneThrough { return $this->hasOneThrough(Post::class, User::class)->latest('posts.id'); }
Country::with('posts')->get();           // 2 queries
```

### The lazy-loading guard (N+1 detector)

```php
foreach (Post::all() as $post) { echo $post->author->name; }   // throws LazyLoadingViolationException
// "Attempted to lazy load [author] on model [Post]. Eager load it with ::with('author') or ->load('author')."
foreach (Post::with('author')->get() as $post) { … }           // fine
```

On by default when `APP_ENV` is `local`/`testing` or `APP_DEBUG=true` (override with `PREVENT_LAZY_LOADING=true|false`,
or `Model::preventLazyLoading(false)`). Production never throws. Models created during the current request are exempt, and
`load()` / calling the relation as a query (`$post->author()->first()`) are explicit and always allowed.

### Soft deletes, events, scopes

```php
$post->delete();               // sets deleted_at; hidden from queries
Post::withTrashed()->find(1);  Post::onlyTrashed()->get();  $post->restore();  $post->forceDelete();

User::creating(fn (User $u) => $u->slug = Str::slug($u->name));   // saving creating created updating updated deleting deleted
// returning false from a "-ing" listener cancels the operation

Post::published()->latest()->paginate(10);
```

### Serialisation

`toArray()` / `toJson()` / `json_encode($model)` apply `$hidden`, `$appends`, casts and loaded relations.
`Paginator` serialises to `{"data": [...], "meta": {"total", "per_page", "current_page", "last_page"}}`.

## Database support

`sqlite`, `mysql` (MariaDB), `pgsql`, `sqlsrv` (SQL Server; needs `pdo_sqlsrv`). SQL differences (quoting, upsert syntax, `LIMIT`/`OFFSET`, `RETURNING`,
auto-increment types) are handled in `Query\Grammar` and `Schema\Schema`. The automated suite runs on SQLite; the
MySQL, PostgreSQL and SQL Server grammars are exercised only by SQL-generation unit tests — run the suite against your own server
before relying on them in production (see [testing](testing.md)).

### Read/write connections

Set `DB_WRITE_HOST` and a comma-separated `DB_READ_HOST` (see `.env.example`). Reads use replicas, writes the primary, on
separate PDO sessions. Reads inside transactions, after a write in the same request (`DB_STICKY`), `FOR UPDATE` and
`RETURNING` queries use the primary; `->useWritePdo()` forces it. If all replicas fail, `DB_READ_FALLBACK` falls back to
the primary. Document stores: [nosql.md](nosql.md).

## Factories & seeders

```bash
php naluz make:factory PostFactory        php naluz make:seeder PostSeeder
php naluz db:seed [--class=Database\Seeders\DatabaseSeeder]      php naluz migrate:fresh --seed
```

```php
// database/factories/PostFactory.php
final class PostFactory extends Factory
{
    protected string $model = Post::class;

    public function definition(): array
    {
        return [
            'user_id' => UserFactory::new(),                 // nested factories are created and their id used
            'title'   => ucfirst($this->fake()->words(4)),
            'body'    => $this->fake()->paragraph(),
        ];
    }
    public function published(): static { return $this->state(['published' => true]); }
}

// models: `use HasFactory;`
User::factory()->create();                                   // persisted        ->make() = not persisted
Post::factory(5)->published()->create(['user_id' => 1]);     // 5 rows with overrides
Post::factory()->state(fn (array $a) => ['title' => strtoupper($a['title'])])->afterCreating(fn ($p) => …)->create();

// database/seeders/DatabaseSeeder.php
public function run(): void { User::factory(10)->create(); $this->call([PostSeeder::class]); }
```

`Naluz\Support\Fake` is a small built-in generator (names, emails, words/sentences, uuid, dates, numbers…);
`Fake::seed(42)` makes data reproducible. Factories use `forceFill()` (trusted code, `$fillable` doesn't apply).
`migrate:fresh` and `db:seed` refuse to run in production without `--force`.
