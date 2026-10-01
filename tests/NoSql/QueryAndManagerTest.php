<?php

declare(strict_types=1);

namespace Naluz\Tests\NoSql;

use Naluz\Config\Repository;
use Naluz\NoSql\DocumentStore;
use Naluz\NoSql\FileStore;
use Naluz\NoSql\MemoryCollection;
use Naluz\NoSql\MemoryStore;
use Naluz\NoSql\NoSqlManager;
use Naluz\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class QueryAndManagerTest extends TestCase
{
    private function users(): \Naluz\NoSql\DocumentCollection
    {
        $c = (new MemoryStore())->collection('users');
        $c->insertMany([
            ['name' => 'Ann', 'age' => 30, 'role' => 'admin', 'email' => 'ann@x.io'],
            ['name' => 'Bob', 'age' => 20, 'role' => 'user', 'email' => 'bob@x.io'],
            ['name' => 'Cy', 'age' => 41, 'role' => 'user', 'email' => 'cy@example.com'],
            ['name' => 'Di', 'age' => 25, 'role' => 'staff'],
        ]);
        return $c;
    }

    // ---------------------------------------------------------------- the compiled filter

    public function testFilterCompilation(): void
    {
        $c = new MemoryCollection();
        $this->assertSame([], $c->query()->toFilter());
        $this->assertSame(['name' => ['$eq' => 'Ann']], $c->query()->where('name', 'Ann')->toFilter(), 'values are always wrapped in $eq');
        $this->assertSame(
            ['$and' => [['age' => ['$gte' => 18]], ['role' => ['$ne' => 'x']]]],
            $c->query()->where('age', '>=', 18)->where('role', '!=', 'x')->toFilter()
        );
        $this->assertSame(
            ['$or' => [['a' => ['$eq' => 1]], ['$and' => [['b' => ['$eq' => 2]], ['c' => ['$eq' => 3]]]]]],
            $c->query()->where('a', 1)->orWhere('b', 2)->where('c', 3)->toFilter()
        );
    }

    public function testBuilderQueries(): void
    {
        $u = $this->users();
        $q = fn () => $u->query();
        $this->assertSame(['Ann', 'Cy'], $q()->where('age', '>', 25)->orderBy('name')->get()->pluck('name')->all());
        $this->assertSame(['Cy', 'Ann'], $q()->where('age', '>', 25)->orderBy('age', 'desc')->pluck('name')->all());
        $this->assertSame(['Ann', 'Di'], $q()->whereIn('role', ['admin', 'staff'])->orderBy('name')->pluck('name')->all());
        $this->assertSame(['Bob', 'Cy'], $q()->whereNotIn('role', ['admin', 'staff'])->orderBy('name')->pluck('name')->all());
        $this->assertSame(['Di'], $q()->whereNull('email')->pluck('name')->all());
        $this->assertSame(3, $q()->whereNotNull('email')->count());
        $this->assertSame(['Di'], $q()->whereBetween('age', [21, 29])->pluck('name')->all());
        $this->assertSame(['Ann', 'Di'], $q()->whereExists('age')->where('role', '!=', 'user')->orderBy('name')->pluck('name')->all());
        $this->assertSame(['Ann', 'Di'], $q()->where('role', 'admin')->orWhere('role', 'staff')->orderBy('name')->pluck('name')->all());
        $this->assertSame('Ann', $q()->where('role', 'admin')->first()['name']);
        $this->assertNull($q()->where('role', 'ghost')->first());
        $this->assertTrue($q()->where('role', 'user')->exists());
        $this->assertFalse($q()->where('role', 'ghost')->exists());
        $this->assertSame(['Bob', 'Di'], $q()->orderBy('age')->limit(2)->pluck('name')->all());
        $this->assertSame(['Ann', 'Cy'], $q()->orderBy('age')->skip(2)->pluck('name')->all());
        $this->assertSame([], $q()->limit(0)->get()->all());
        $this->assertSame(['name' => 'Ann'], array_diff_key($q()->select('name')->where('name', 'Ann')->first(), ['_id' => 1]));
    }

    public function testLikeIsEscapedAndAnchored(): void
    {
        $u = $this->users();
        $this->assertSame(['ann@x.io', 'bob@x.io'], $u->query()->where('email', 'like', '%@x.io')->orderBy('email')->pluck('email')->all());
        $this->assertSame(['Ann'], $u->query()->where('name', 'like', 'A__')->pluck('name')->all());
        $this->assertSame(['Ann'], $u->query()->where('name', 'ilike', 'ANN')->pluck('name')->all());
        $this->assertSame([], $u->query()->where('name', 'like', 'An')->pluck('name')->all(), 'anchored: no partial match without %');
        $this->assertSame([], $u->query()->where('email', 'like', '.*')->pluck('email')->all(), 'regex metacharacters in the pattern are literals');
        $this->assertSame([], $u->query()->where('name', 'like', '(a+)+$')->pluck('name')->all());
    }

    public function testPaginate(): void
    {
        $page = $this->users()->query()->orderBy('age')->paginate(2, 2);
        $this->assertSame(4, $page->total);
        $this->assertSame(['Ann', 'Cy'], $page->items->pluck('name')->all());
        $this->assertFalse($page->hasMorePages());
        $this->assertSame(2, $page->lastPage());
    }

    public function testWrites(): void
    {
        $u = $this->users();
        $r = $u->query()->where('role', 'user')->update(['flag' => true]);
        $this->assertSame(2, $r->modified);
        $u->query()->where('name', 'Ann')->increment('age', 5);
        $this->assertSame(35, $u->findOne(['name' => 'Ann'])['age']);
        $u->query()->where('name', 'Ann')->unsetFields('email');
        $this->assertArrayNotHasKey('email', $u->findOne(['name' => 'Ann']));
        $u->query()->insert(['name' => 'Ed']);
        $this->assertSame(1, $u->query()->where('name', 'Ed')->delete());
        $this->assertSame(4, $u->count());
    }

    public function testDatesEnumsAndStringablesBecomeScalars(): void
    {
        $c = new MemoryCollection();
        $q = $c->query()->where('at', '>', new \DateTimeImmutable('2026-01-01T00:00:00+00:00'));
        $this->assertSame('2026-01-01T00:00:00+00:00', $q->toFilter()['at']['$gt']);
        $this->expectException(\InvalidArgumentException::class);
        $c->query()->where('x', new \stdClass());
    }

    // ---------------------------------------------------------------- builder safety

    #[DataProvider('hostileFieldNames')]
    public function testHostileFieldNamesAreRejected(string $field): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new MemoryCollection())->query()->where($field, 1);
    }

    public static function hostileFieldNames(): array
    {
        return [['$where'], ['$or'], ['a.$ne'], ['a..b'], [''], [".a"], ["a\0"]];
    }

    public function testOperatorsAndDirectionsAreAllowListed(): void
    {
        foreach ([fn ($q) => $q->where('a', '=~', 1), fn ($q) => $q->where('a', 'regex', '.*'), fn ($q) => $q->orderBy('a', 'sideways'), fn ($q) => $q->orderBy('$x')] as $bad) {
            try {
                $bad((new MemoryCollection())->query());
                $this->fail('accepted an unsupported operator/direction');
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testInjectedUpdateFieldsAreRejected(): void
    {
        $u = $this->users();
        $this->expectException(\InvalidArgumentException::class);
        $u->query()->where('name', 'Ann')->update(['$set' => ['role' => 'root']]);
    }

    public function testNestedOperatorValuesCannotBeStored(): void
    {
        $u = $this->users();
        $this->expectException(\InvalidArgumentException::class);
        $u->query()->where('name', 'Ann')->update(['profile' => ['$ne' => 1]]);
    }

    // ---------------------------------------------------------------- manager, config, helper

    public function testManagerResolvesConfiguredConnections(): void
    {
        $dir = sys_get_temp_dir() . '/naluz-nosql-m-' . bin2hex(random_bytes(4));
        $cfg = new Repository(['nosql' => ['default' => 'mem', 'connections' => [
            'mem' => ['driver' => 'memory'],
            'files' => ['driver' => 'file', 'path' => $dir],
            'bad' => ['driver' => 'cassandra'],
        ]]]);
        $m = new NoSqlManager($cfg, '/base');
        $this->assertInstanceOf(MemoryStore::class, $m->connection());
        $this->assertSame($m->connection('mem'), $m->connection('mem'), 'connections are built once');
        $this->assertInstanceOf(FileStore::class, $m->connection('files'));
        $m->collection('t', 'files')->insertOne(['a' => 1]);
        $this->assertFileExists($dir . '/t.json');
        array_map('unlink', glob($dir . '/*'));
        rmdir($dir);
        foreach (['bad', 'missing'] as $name) {
            try {
                $m->connection($name);
                $this->fail();
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testRelativeFilePathsResolveAgainstTheProject(): void
    {
        $base = sys_get_temp_dir() . '/naluz-base-' . bin2hex(random_bytes(4));
        $m = new NoSqlManager(new Repository(['nosql' => ['default' => 'f', 'connections' => ['f' => ['driver' => 'file', 'path' => 'storage/nosql']]]]), $base);
        $m->collection('x')->insertOne(['a' => 1]);
        $this->assertFileExists($base . '/storage/nosql/x.json');
        array_map('unlink', glob($base . '/storage/nosql/*'));
        rmdir($base . '/storage/nosql');
        rmdir($base . '/storage');
        rmdir($base);
    }

    public function testApplicationWiringAndHelper(): void
    {
        $this->app->make(Repository::class)->set('nosql.default', 'memory');
        $this->assertInstanceOf(NoSqlManager::class, $this->app->make(NoSqlManager::class));
        $this->assertInstanceOf(DocumentStore::class, nosql());
        nosql()->collection('log')->insertOne(['m' => 'hello']);
        $this->assertSame(1, nosql()->collection('log')->count());
        $this->assertInstanceOf(DocumentStore::class, $this->app->make(DocumentStore::class));
    }

    public function testShippedConfigDefaultsToTheFileDriverAndHasMongo(): void
    {
        $cfg = require dirname(__DIR__, 2) . '/config/nosql.php';
        $this->assertSame('file', $cfg['default']);
        $this->assertSame(['file', 'memory', 'mongodb'], array_keys($cfg['connections']));
    }

    public function testMongoDriverExplainsWhatIsMissing(): void
    {
        if (class_exists('MongoDB\\Client')) {
            $this->markTestSkipped('the MongoDB library is installed');
        }
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('mongodb/mongodb');
        (new NoSqlManager(new Repository(['nosql' => ['default' => 'm', 'connections' => ['m' => ['driver' => 'mongodb']]]]), '/b'))->connection();
    }
}
