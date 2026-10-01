<?php

declare(strict_types=1);

namespace Naluz\Tests\NoSql;

use Naluz\NoSql\DocumentCollection;
use Naluz\NoSql\DuplicateKeyException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Behaviour every document-collection driver must provide. */
abstract class CollectionContract extends TestCase
{
    abstract protected function collection(): DocumentCollection;

    protected function seeded(): DocumentCollection
    {
        $c = $this->collection();
        $c->insertMany([
            ['name' => 'Ann', 'age' => 30, 'role' => 'admin', 'tags' => ['php', 'sql'], 'address' => ['city' => 'Manila', 'zip' => '1000'], 'score' => 90.5],
            ['name' => 'Bob', 'age' => 20, 'role' => 'user', 'tags' => ['js'], 'address' => ['city' => 'Cebu'], 'score' => 70],
            ['name' => 'Cy', 'age' => 41, 'role' => 'user', 'tags' => [], 'address' => ['city' => 'Manila'], 'nickname' => null],
            ['name' => 'Di', 'age' => 25, 'role' => 'staff', 'tags' => ['php', 'go', 'sql'], 'active' => true],
        ]);
        return $c;
    }

    private function names(array $docs): array
    {
        return array_column($docs, 'name');
    }

    // ---------------------------------------------------------------- insert & read

    public function testInsertGeneratesIdsAndFindReturnsDocuments(): void
    {
        $c = $this->collection();
        $id = $c->insertOne(['name' => 'Ann']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{24}$/', (string) $id);
        $doc = $c->findOne(['_id' => $id]);
        $this->assertSame('Ann', $doc['name']);
        $this->assertSame($id, $doc['_id']);
        $this->assertNull($c->findOne(['name' => 'nobody']));
    }

    public function testCustomIdsAndDuplicateIds(): void
    {
        $c = $this->collection();
        $this->assertSame(7, $c->insertOne(['_id' => 7, 'a' => 1]));
        $this->assertSame('x', $c->insertOne(['_id' => 'x']));
        $this->expectException(DuplicateKeyException::class);
        $c->insertOne(['_id' => 7]);
    }

    public function testIntegerAndStringIdsDoNotCollide(): void
    {
        $c = $this->collection();
        $c->insertOne(['_id' => 1]);
        $c->insertOne(['_id' => '1']);
        $this->assertSame(2, $c->count());
    }

    public function testInsertManyIsAllOrNothing(): void
    {
        $c = $this->collection();
        $c->insertOne(['_id' => 'dup']);
        try {
            $c->insertMany([['_id' => 'a'], ['_id' => 'dup'], ['_id' => 'b']]);
            $this->fail();
        } catch (DuplicateKeyException) {
        }
        $this->assertSame(1, $c->count(), 'nothing from the failed batch was stored');
    }

    public function testNestedDocumentsAndTypesSurvive(): void
    {
        $c = $this->collection();
        $id = $c->insertOne(['n' => 1, 'f' => 1.5, 'b' => true, 'z' => null, 's' => 'héllo ✓', 'list' => [1, [2, 3]], 'o' => ['a' => ['b' => 'deep']], 'big' => 9007199254740991]);
        $doc = $c->findOne(['_id' => $id]);
        $this->assertSame(1, $doc['n']);
        $this->assertSame(1.5, $doc['f']);
        $this->assertTrue($doc['b']);
        $this->assertNull($doc['z']);
        $this->assertSame('héllo ✓', $doc['s']);
        $this->assertSame([1, [2, 3]], $doc['list']);
        $this->assertSame('deep', $doc['o']['a']['b']);
        $this->assertSame(9007199254740991, $doc['big']);
    }

    // ---------------------------------------------------------------- operators

    public function testEqualityAndComparison(): void
    {
        $c = $this->seeded();
        $this->assertSame(['Ann'], $this->names($c->find(['name' => 'Ann'])));
        $this->assertSame(['Ann', 'Cy'], $this->names($c->find(['age' => ['$gt' => 25]])));
        $this->assertSame(['Ann', 'Cy', 'Di'], $this->names($c->find(['age' => ['$gte' => 25]])));
        $this->assertSame(['Bob'], $this->names($c->find(['age' => ['$lt' => 25]])));
        $this->assertSame(['Bob', 'Di'], $this->names($c->find(['age' => ['$lte' => 25]])));
        $this->assertSame(['Di'], $this->names($c->find(['age' => ['$gte' => 21, '$lt' => 30]])));
        $this->assertSame(['Ann', 'Bob', 'Cy'], $this->names($c->find(['age' => ['$ne' => 25]])));
    }

    public function testTypesAreNotCoerced(): void
    {
        $c = $this->seeded();
        $this->assertCount(0, $c->find(['age' => '30']), 'a string does not match a number');
        $this->assertCount(0, $c->find(['age' => ['$gt' => 'abc']]), 'values of different types are not ordered against each other');
        $this->assertCount(1, $c->find(['active' => true]));
        $this->assertCount(0, $c->find(['active' => 1]));
    }

    public function testInNinExistsAndNull(): void
    {
        $c = $this->seeded();
        $this->assertSame(['Ann', 'Di'], $this->names($c->find(['role' => ['$in' => ['admin', 'staff']]])));
        $this->assertSame(['Bob', 'Cy'], $this->names($c->find(['role' => ['$nin' => ['admin', 'staff']]])));
        $this->assertSame(['Di'], $this->names($c->find(['active' => ['$exists' => true]])));
        $this->assertCount(3, $c->find(['active' => ['$exists' => false]]));
        $this->assertSame(['Ann', 'Bob', 'Cy', 'Di'], $this->names($c->find(['missing' => null])), 'null matches missing fields');
        $this->assertSame(['Cy'], $this->names($c->find(['nickname' => ['$exists' => true]])));
    }

    public function testLogicalOperators(): void
    {
        $c = $this->seeded();
        $this->assertSame(['Ann', 'Bob'], $this->names($c->find(['$or' => [['role' => 'admin'], ['age' => 20]]])));
        $this->assertSame(['Cy'], $this->names($c->find(['$and' => [['role' => 'user'], ['age' => ['$gt' => 30]]]])));
        $this->assertSame(['Bob', 'Cy'], $this->names($c->find(['$nor' => [['role' => 'admin'], ['role' => 'staff']]])));
        $this->assertSame(['Bob'], $this->names($c->find(['role' => 'user', 'age' => ['$not' => ['$gt' => 25]]])));
    }

    public function testDottedPathsAndArrays(): void
    {
        $c = $this->seeded();
        $this->assertSame(['Ann', 'Cy'], $this->names($c->find(['address.city' => 'Manila'])));
        $this->assertSame(['Ann', 'Di'], $this->names($c->find(['tags' => 'php'])), 'array fields match on any element');
        $this->assertSame(['Ann', 'Di'], $this->names($c->find(['tags' => ['$in' => ['php', 'rust']]])));
        $this->assertSame(['Ann', 'Di'], $this->names($c->find(['tags' => ['$all' => ['php', 'sql']]])));
        $this->assertSame(['Cy'], $this->names($c->find(['tags' => ['$size' => 0]])));
        $this->assertSame(['Di'], $this->names($c->find(['tags' => ['$size' => 3]])));
        $this->assertSame(['Ann'], $this->names($c->find(['tags.0' => 'php', 'address.zip' => '1000'])));
    }

    public function testElemMatch(): void
    {
        $c = $this->collection();
        $c->insertMany([
            ['n' => 'a', 'items' => [['sku' => 'x', 'qty' => 1], ['sku' => 'y', 'qty' => 5]]],
            ['n' => 'b', 'items' => [['sku' => 'x', 'qty' => 9]]],
            ['n' => 'c', 'scores' => [1, 7, 3]],
        ]);
        $this->assertSame(['a'], array_column($c->find(['items' => ['$elemMatch' => ['sku' => 'y', 'qty' => ['$gte' => 5]]]]), 'n'));
        $this->assertSame(['a', 'b'], array_column($c->find(['items' => ['$elemMatch' => ['sku' => 'x']]]), 'n'));
        $this->assertSame(['c'], array_column($c->find(['scores' => ['$elemMatch' => ['$gt' => 5]]]), 'n'));
    }

    public function testRegex(): void
    {
        $c = $this->seeded();
        $this->assertSame(['Ann'], $this->names($c->find(['name' => ['$regex' => '^A']])));
        $this->assertSame(['Ann'], $this->names($c->find(['name' => ['$regex' => 'N', '$options' => 'i']])), 'case-insensitive');
        $this->assertSame([], $this->names($c->find(['name' => ['$regex' => 'N']])), 'case-sensitive by default');
        $this->assertSame(['Bob'], $this->names($c->find(['name' => ['$regex' => '^b.b$', '$options' => 'i']])));
        $this->assertSame(['Ann', 'Di'], $this->names($c->find(['tags' => ['$regex' => '^p']])), 'matches array elements');
        $this->assertSame(['Ann', 'Bob', 'Cy', 'Di'], $this->names($c->find(['name' => ['$regex' => '[~]|.']])), 'the delimiter character cannot break out of the pattern');
    }

    // ---------------------------------------------------------------- options

    public function testSortSkipLimit(): void
    {
        $c = $this->seeded();
        $this->assertSame(['Bob', 'Di', 'Ann', 'Cy'], $this->names($c->find([], ['sort' => ['age' => 1]])));
        $this->assertSame(['Cy', 'Ann', 'Di', 'Bob'], $this->names($c->find([], ['sort' => ['age' => -1]])));
        $this->assertSame(['Ann', 'Di'], $this->names($c->find([], ['sort' => ['age' => -1], 'skip' => 1, 'limit' => 2])));
    }

    public function testMultiKeySort(): void
    {
        $c = $this->seeded();
        $this->assertSame(['Ann', 'Di', 'Bob', 'Cy'], $this->names($c->find([], ['sort' => ['role' => 1, 'age' => 1]])));
        $this->assertSame(['Bob', 'Cy', 'Di', 'Ann'], $this->names($c->find([], ['sort' => ['role' => -1, 'age' => 1]])));
    }

    public function testProjection(): void
    {
        $c = $this->seeded();
        $doc = $c->findOne(['name' => 'Ann'], ['projection' => ['name' => 1, 'address.city' => 1]]);
        $this->assertSame(['_id', 'name', 'address'], array_keys($doc));
        $this->assertSame(['city' => 'Manila'], $doc['address']);
        $doc = $c->findOne(['name' => 'Ann'], ['projection' => ['name' => 1, '_id' => 0]]);
        $this->assertSame(['name' => 'Ann'], $doc);
        $doc = $c->findOne(['name' => 'Ann'], ['projection' => ['tags' => 0, 'address' => 0, 'score' => 0]]);
        $this->assertArrayNotHasKey('tags', $doc);
        $this->assertSame('Ann', $doc['name']);
    }

    public function testCountAndDistinct(): void
    {
        $c = $this->seeded();
        $this->assertSame(4, $c->count());
        $this->assertSame(2, $c->count(['role' => 'user']));
        $this->assertEqualsCanonicalizing(['admin', 'user', 'staff'], $c->distinct('role'));
        $this->assertEqualsCanonicalizing(['php', 'sql', 'js', 'go'], $c->distinct('tags'));
        $this->assertSame(['Cebu'], $c->distinct('address.city', ['role' => 'user', 'age' => ['$lt' => 25]]));
    }

    // ---------------------------------------------------------------- updates

    public function testSetUnsetIncMulMinMax(): void
    {
        $c = $this->seeded();
        $r = $c->updateOne(['name' => 'Ann'], ['$set' => ['age' => 31, 'address.zip' => '2000', 'new.deep.field' => 1], '$unset' => ['score' => ''], '$inc' => ['visits' => 2]]);
        $this->assertSame(1, $r->matched);
        $this->assertSame(1, $r->modified);
        $d = $c->findOne(['name' => 'Ann']);
        $this->assertSame(31, $d['age']);
        $this->assertSame('2000', $d['address']['zip']);
        $this->assertSame(1, $d['new']['deep']['field']);
        $this->assertArrayNotHasKey('score', $d);
        $this->assertSame(2, $d['visits']);

        $c->updateOne(['name' => 'Ann'], ['$inc' => ['visits' => 3, 'age' => -1], '$mul' => ['visits' => 2], '$min' => ['age' => 10], '$max' => ['best' => 5]]);
        $d = $c->findOne(['name' => 'Ann']);
        $this->assertSame(10, $d['age']);
        $this->assertSame(10, $d['visits']);
        $this->assertSame(5, $d['best']);
    }

    public function testArrayOperators(): void
    {
        $c = $this->seeded();
        $c->updateOne(['name' => 'Bob'], ['$push' => ['tags' => 'ts'], '$addToSet' => ['seen' => 'a']]);
        $c->updateOne(['name' => 'Bob'], ['$push' => ['tags' => ['$each' => ['x', 'y']]], '$addToSet' => ['seen' => 'a']]);
        $d = $c->findOne(['name' => 'Bob']);
        $this->assertSame(['js', 'ts', 'x', 'y'], $d['tags']);
        $this->assertSame(['a'], $d['seen'], '$addToSet does not add duplicates');
        $c->updateOne(['name' => 'Bob'], ['$pull' => ['tags' => 'x']]);
        $this->assertSame(['js', 'ts', 'y'], $c->findOne(['name' => 'Bob'])['tags']);

        $c->insertOne(['n' => 'cart', 'items' => [['sku' => 'a', 'q' => 1], ['sku' => 'b', 'q' => 2]]]);
        $c->updateOne(['n' => 'cart'], ['$pull' => ['items' => ['sku' => 'a']]]);
        $this->assertSame([['sku' => 'b', 'q' => 2]], $c->findOne(['n' => 'cart'])['items']);
    }

    public function testRename(): void
    {
        $c = $this->seeded();
        $c->updateOne(['name' => 'Ann'], ['$rename' => ['role' => 'job']]);
        $d = $c->findOne(['name' => 'Ann']);
        $this->assertSame('admin', $d['job']);
        $this->assertArrayNotHasKey('role', $d);
    }

    public function testUpdateManyAndModifiedCounts(): void
    {
        $c = $this->seeded();
        $r = $c->updateMany(['role' => 'user'], ['$set' => ['flag' => 1]]);
        $this->assertSame([2, 2], [$r->matched, $r->modified]);
        $r = $c->updateMany(['role' => 'user'], ['$set' => ['flag' => 1]]);
        $this->assertSame([2, 0], [$r->matched, $r->modified], 'setting the same value modifies nothing');
        $r = $c->updateOne(['name' => 'nobody'], ['$set' => ['x' => 1]]);
        $this->assertSame([0, 0], [$r->matched, $r->modified]);
        $this->assertNull($r->upsertedId);
    }

    public function testUpsertSeedsFromEqualityFilter(): void
    {
        $c = $this->collection();
        $r = $c->updateOne(['email' => 'a@x.io', 'plan' => ['$eq' => 'free'], 'age' => ['$gt' => 1]], ['$set' => ['name' => 'A'], '$setOnInsert' => ['created' => 1]], true);
        $this->assertNotNull($r->upsertedId);
        $d = $c->findOne(['_id' => $r->upsertedId]);
        $this->assertSame(['email' => 'a@x.io', 'plan' => 'free', 'name' => 'A', 'created' => 1], array_diff_key($d, ['_id' => 1]));

        $r = $c->updateOne(['email' => 'a@x.io'], ['$set' => ['name' => 'B'], '$setOnInsert' => ['created' => 2]], true);
        $this->assertNull($r->upsertedId, 'existing document: no insert');
        $this->assertSame(1, $c->findOne(['email' => 'a@x.io'])['created'], '$setOnInsert only applies on insert');
        $this->assertSame(1, $c->count());
    }

    public function testReplaceOneKeepsId(): void
    {
        $c = $this->seeded();
        $id = $c->findOne(['name' => 'Bob'])['_id'];
        $c->replaceOne(['name' => 'Bob'], ['name' => 'Robert', 'only' => 'this']);
        $d = $c->findOne(['_id' => $id]);
        $this->assertSame(['_id' => $id, 'name' => 'Robert', 'only' => 'this'], $d);
        $this->assertNotNull($c->replaceOne(['name' => 'ghost'], ['name' => 'Ghost'], true)->upsertedId);
    }

    public function testIdIsImmutable(): void
    {
        $c = $this->seeded();
        $this->expectException(\InvalidArgumentException::class);
        $c->updateOne(['name' => 'Ann'], ['$set' => ['_id' => 'other']]);
    }

    // ---------------------------------------------------------------- delete & indexes

    public function testDelete(): void
    {
        $c = $this->seeded();
        $this->assertSame(1, $c->deleteOne(['role' => 'user']));
        $this->assertSame(3, $c->count());
        $this->assertSame(2, $c->deleteMany(['age' => ['$gte' => 30]]));
        $this->assertSame(0, $c->deleteMany(['nope' => 1]));
        $this->assertSame(1, $c->count());
        $c->drop();
        $this->assertSame(0, $c->count());
    }

    public function testUniqueIndexIsEnforcedOnInsertUpdateAndReplace(): void
    {
        $c = $this->collection();
        $c->createIndex(['email' => 1], ['unique' => true]);
        $c->insertOne(['email' => 'a@x.io']);
        $c->insertOne(['email' => 'b@x.io']);
        foreach (
            [
            fn () => $c->insertOne(['email' => 'a@x.io']),
            fn () => $c->updateOne(['email' => 'b@x.io'], ['$set' => ['email' => 'a@x.io']]),
            fn () => $c->replaceOne(['email' => 'b@x.io'], ['email' => 'a@x.io']),
            fn () => $c->updateOne(['email' => 'c@x.io'], ['$set' => ['n' => 1]], true) ?: throw new DuplicateKeyException('x'),
            ] as $i => $attempt
        ) {
            try {
                $attempt();
                if ($i !== 3) {
                    $this->fail("duplicate #{$i} was accepted");
                }
            } catch (DuplicateKeyException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame(['a@x.io', 'b@x.io', 'c@x.io'], array_column($c->find([], ['sort' => ['email' => 1]]), 'email'));
        $c->updateOne(['email' => 'a@x.io'], ['$set' => ['name' => 'same doc, fine']]);
    }

    public function testCompoundUniqueIndexAndExistingDuplicatesAreRefused(): void
    {
        $c = $this->collection();
        $c->createIndex(['a' => 1, 'b' => 1], ['unique' => true]);
        $c->insertOne(['a' => 1, 'b' => 1]);
        $c->insertOne(['a' => 1, 'b' => 2]);
        $this->expectException(DuplicateKeyException::class);
        $c->insertOne(['a' => 1, 'b' => 1]);
    }

    public function testCannotCreateUniqueIndexOverExistingDuplicates(): void
    {
        $c = $this->collection();
        $c->insertMany([['k' => 1], ['k' => 1]]);
        $this->expectException(DuplicateKeyException::class);
        $c->createIndex(['k' => 1], ['unique' => true]);
    }

    // ---------------------------------------------------------------- safety

    #[DataProvider('unsafeDocuments')]
    public function testDocumentsCannotCarryOperatorLikeOrDottedFieldNames(array $doc): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->collection()->insertOne($doc);
    }

    public static function unsafeDocuments(): array
    {
        return [
            [['$set' => 1]], [['a' => ['$ne' => 1]]], [['a.b' => 1]], [['' => 1]], [['list' => [['$gt' => 1]]]], [["a\0b" => 1]],
        ];
    }

    #[DataProvider('badFilters')]
    public function testUnknownOrMalformedOperatorsThrowInsteadOfMatchingEverything(array $filter): void
    {
        $c = $this->seeded();
        $this->expectException(\InvalidArgumentException::class);
        $c->find($filter);
    }

    public static function badFilters(): array
    {
        return [
            [['age' => ['$bogus' => 1]]], [['$where' => 'sleep(1000)']], [['$or' => []]], [['$or' => 'x']], [['$and' => [1]]],
            [['age' => ['$in' => 'not-a-list']]], [['age' => ['$gt' => 1, 'plain' => 2]]], [['name' => ['$regex' => 'a', '$options' => 'z']]],
            [['name' => ['$regex' => str_repeat('a', 2000)]]],
        ];
    }

    public function testBadUpdatesAreRejected(): void
    {
        $c = $this->seeded();
        foreach ([[], ['name' => 'plain'], ['$bogus' => ['a' => 1]], ['$set' => 'x'], ['$set' => ['$bad' => 1]], ['$inc' => ['name' => 1]], ['$inc' => ['age' => 'x']], ['$push' => ['name' => 1]]] as $update) {
            try {
                $c->updateOne(['name' => 'Ann'], $update);
                $this->fail('accepted bad update ' . json_encode($update));
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame('Ann', $c->findOne(['age' => 30])['name'], 'nothing was changed by the rejected updates');
    }

    public function testQueryBuilderTreatsInputAsLiteralsNotOperators(): void
    {
        $c = $this->collection();
        $c->insertMany([['user' => 'admin', 'password' => 'secret'], ['user' => 'bob', 'password' => 'hunter2']]);

        // classic NoSQL injection: login with password[$ne]= (i.e. ['$ne' => null]) bypasses the check when used as a raw filter
        $attack = ['$ne' => null];
        $this->assertCount(2, $c->find(['password' => $attack]), 'as a RAW filter the operator works — that is the vulnerability');
        $this->assertNull($c->query()->where('user', 'admin')->where('password', $attack)->first(), 'the builder matches it as a literal value');
        $this->assertSame(0, $c->query()->where('password', $attack)->count());
        $this->assertSame(0, $c->query()->whereIn('user', [['$ne' => 1]])->count());
        $this->assertSame('bob', $c->query()->where('user', 'bob')->where('password', 'hunter2')->first()['user'], 'honest input still works');
    }
}
