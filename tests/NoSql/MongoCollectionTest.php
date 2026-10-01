<?php

declare(strict_types=1);

namespace Naluz\Tests\NoSql;

use Naluz\NoSql\MongoCollection;
use Naluz\NoSql\MongoStore;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** A stand-in with the same method names as MongoDB\Collection; it records every call and returns canned results. */
final class FakeMongoCollection
{
    /** @var list<array{0:string,1:array}> */
    public array $calls = [];
    public mixed $findResult = [];

    public function __call(string $method, array $args): mixed
    {
        $this->calls[] = [$method, $args];
        return match ($method) {
            'insertOne' => new class ($args[0]['_id'] ?? 'generated-id') {
                public function __construct(private mixed $id)
                {
                }

                public function getInsertedId(): mixed
                {
                    return $this->id;
                }
            },
            'insertMany' => new class {
                public function getInsertedIds(): array
                {
                    return [0 => 'a', 1 => 'b'];
                }
            },
            'find' => new \ArrayIterator((array) $this->findResult),
            'countDocuments' => 7,
            'distinct' => ['x', 'y'],
            'updateOne', 'updateMany', 'replaceOne' => new class {
                public function getMatchedCount(): int
                {
                    return 2;
                }

                public function getModifiedCount(): int
                {
                    return 1;
                }

                public function getUpsertedId(): mixed
                {
                    return null;
                }
            },
            'deleteOne', 'deleteMany' => new class {
                public function getDeletedCount(): int
                {
                    return 3;
                }
            },
            default => null,
        };
    }
}

final class MongoCollectionTest extends TestCase
{
    private FakeMongoCollection $fake;
    private MongoCollection $c;

    protected function setUp(): void
    {
        $this->fake = new FakeMongoCollection();
        $this->c = new MongoCollection($this->fake);
    }

    public function testFindPassesFilterAndOptionsAndReturnsArrays(): void
    {
        $this->fake->findResult = [['_id' => 'a', 'n' => 1], ['_id' => 'b', 'n' => 2]];
        $docs = $this->c->find(['n' => ['$gt' => 0]], ['sort' => ['n' => -1], 'limit' => 5, 'skip' => 2, 'projection' => ['n' => 1]]);
        $this->assertSame([['_id' => 'a', 'n' => 1], ['_id' => 'b', 'n' => 2]], $docs);
        [$method, $args] = $this->fake->calls[0];
        $this->assertSame('find', $method);
        $this->assertSame(['n' => ['$gt' => 0]], $args[0]);
        $this->assertSame(['n' => -1], $args[1]['sort']);
        $this->assertSame(5, $args[1]['limit']);
        $this->assertSame(2, $args[1]['skip']);
        $this->assertSame(['n' => 1], $args[1]['projection']);
        $this->assertSame('array', $args[1]['typeMap']['root'], 'documents come back as arrays');
    }

    public function testInsertCountDistinctDelete(): void
    {
        $this->assertSame('generated-id', $this->c->insertOne(['a' => 1]));
        $this->assertSame(['a', 'b'], $this->c->insertMany([['a' => 1], ['a' => 2]]));
        $this->assertSame(7, $this->c->count(['a' => 1]));
        $this->assertSame(['x', 'y'], $this->c->distinct('a'));
        $this->assertSame(3, $this->c->deleteOne(['a' => 1]));
        $this->assertSame(3, $this->c->deleteMany([]));
    }

    public function testUpdateResultsAreTranslated(): void
    {
        $r = $this->c->updateOne(['a' => 1], ['$set' => ['b' => 2]], true);
        $this->assertSame([2, 1, null], [$r->matched, $r->modified, $r->upsertedId]);
        $this->assertSame(['upsert' => true], $this->fake->calls[0][1][2]);
        $this->c->replaceOne(['a' => 1], ['a' => 2]);
        $this->assertSame('replaceOne', $this->fake->calls[1][0]);
    }

    public function testIndexOptions(): void
    {
        $this->c->createIndex(['email' => 1], ['unique' => true]);
        $this->assertSame([['email' => 1], ['unique' => true]], $this->fake->calls[0][1]);
        $this->c->drop();
        $this->assertSame('drop', $this->fake->calls[1][0]);
    }

    public function testQueryBuilderWorksOnTopOfTheAdapter(): void
    {
        $this->c->query()->where('age', '>=', 18)->orderBy('name')->limit(3)->get();
        [, $args] = $this->fake->calls[0];
        $this->assertSame(['age' => ['$gte' => 18]], $args[0]);
        $this->assertSame(['name' => 1], $args[1]['sort']);
        $this->c->query()->where('password', ['$ne' => null])->count();
        $this->assertSame(['password' => ['$eq' => ['$ne' => null]]], $this->fake->calls[1][1][0], 'injection stays a literal on the wire');
    }

    // ---------------------------------------------------------------- safety on the wire

    #[DataProvider('forbiddenFilters')]
    public function testServerSideJavascriptIsNeverSent(array $filter): void
    {
        try {
            $this->c->find($filter);
            $this->fail('forbidden operator reached the database');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('not allowed', $e->getMessage());
        }
        $this->assertSame([], $this->fake->calls, 'nothing was sent to MongoDB');
    }

    public static function forbiddenFilters(): array
    {
        return [
            [['$where' => 'sleep(5000)']],
            [['$or' => [['$where' => 'this.a == 1']]]],
            [['a' => ['$function' => ['body' => 'function(){return true}', 'args' => [], 'lang' => 'js']]]],
            [['x' => ['$and' => [['$accumulator' => []]]]]],
        ];
    }

    public function testUnsafeDocumentsAndUpdatesAreRefusedBeforeSending(): void
    {
        foreach (
            [
            fn () => $this->c->insertOne(['$set' => 1]),
            fn () => $this->c->insertOne(['a' => ['$ne' => 1]]),
            fn () => $this->c->insertMany([['ok' => 1], ['a.b' => 1]]),
            fn () => $this->c->updateOne([], ['name' => 'plain']),
            fn () => $this->c->updateOne([], ['$where' => ['x' => 1]]),
            fn () => $this->c->updateOne([], ['$set' => ['$bad' => 1]]),
            fn () => $this->c->updateOne([], ['$set' => ['a' => ['$ne' => 1]]]),
            fn () => $this->c->updateOne([], ['$push' => ['a' => ['$each' => [['$x' => 1]]]]]),
            fn () => $this->c->replaceOne([], ['$set' => 1]),
            fn () => $this->c->createIndex(['$x' => 1]),
            fn () => $this->c->distinct('$where'),
            ] as $i => $attempt
        ) {
            try {
                $attempt();
                $this->fail("unsafe call #{$i} reached the database");
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame([], $this->fake->calls);
    }

    public function testIdsThatLookLikeObjectIdsAreConvertedWhenTheExtensionIsLoaded(): void
    {
        $hex = str_repeat('ab', 12);
        $this->c->findOne(['_id' => $hex]);
        $sent = $this->fake->calls[0][1][0]['_id'];
        if (class_exists('MongoDB\\BSON\\ObjectId')) {
            $this->assertInstanceOf('MongoDB\\BSON\\ObjectId', $sent);
        } else {
            $this->assertSame($hex, $sent, 'without ext-mongodb ids stay strings');
        }
        $this->fake->calls = [];
        $this->c->findOne(['_id' => 'not-an-objectid']);
        $this->assertSame('not-an-objectid', $this->fake->calls[0][1][0]['_id']);
    }

    public function testBsonValuesAreConvertedToPhpValues(): void
    {
        $date = new class {
            public function toDateTime(): \DateTimeImmutable
            {
                return new \DateTimeImmutable('2026-05-05T10:00:00+00:00');
            }
        };
        $oid = new class {
            public function __toString(): string
            {
                return 'abc123';
            }
        };
        $this->fake->findResult = [['_id' => $oid, 'created' => $date, 'nested' => ['id' => $oid]]];
        $doc = $this->c->find()[0];
        $this->assertSame('abc123', $doc['_id']);
        $this->assertSame('2026-05-05T10:00:00+00:00', $doc['created']);
        $this->assertSame('abc123', $doc['nested']['id']);
    }

    public function testStoreWrapsDatabaseAndValidatesNames(): void
    {
        $db = new class {
            public array $selected = [];
            public array $dropped = [];

            public function selectCollection(string $n): object
            {
                $this->selected[] = $n;
                return new FakeMongoCollection();
            }

            public function listCollectionNames(): \ArrayIterator
            {
                return new \ArrayIterator(['a', 'b']);
            }

            public function dropCollection(string $n): void
            {
                $this->dropped[] = $n;
            }
        };
        $store = new MongoStore($db);
        $this->assertInstanceOf(MongoCollection::class, $store->collection('users'));
        $this->assertSame(['a', 'b'], $store->collections());
        $store->dropCollection('users');
        $this->assertSame(['users'], $db->dropped);
        $this->expectException(\InvalidArgumentException::class);
        $store->collection('system.$cmd/../x');
    }
}
