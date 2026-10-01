<?php

declare(strict_types=1);

namespace Naluz\Tests\Database;

use Naluz\Database\Connection;
use Naluz\Database\DatabaseManager;
use Naluz\Database\Paginator;
use Naluz\Database\QueryException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class QueryBuilderTest extends TestCase
{
    private Connection $db;

    protected function setUp(): void
    {
        $this->db = (new DatabaseManager([
            'default' => 't',
            'connections' => ['t' => ['driver' => 'sqlite', 'database' => ':memory:']],
        ]))->connection();
        $s = $this->db->schema();
        $s->create('users', function ($t) {
            $t->id();
            $t->string('name');
            $t->string('email')->unique();
            $t->integer('age')->nullable();
            $t->string('role')->default('user');
        });
        $s->create('orders', function ($t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->decimal('total', 10, 2);
        });
        $this->db->table('users')->insert([
            ['name' => 'Ann', 'email' => 'ann@x.io', 'age' => 30, 'role' => 'admin'],
            ['name' => 'Bob', 'email' => 'bob@x.io', 'age' => 20, 'role' => 'user'],
            ['name' => 'Cy', 'email' => 'cy@x.io', 'age' => null, 'role' => 'user'],
        ]);
        $this->db->table('orders')->insert([
            ['user_id' => 1, 'total' => 10.5], ['user_id' => 1, 'total' => 20], ['user_id' => 2, 'total' => 5],
        ]);
    }

    public function testSqlGenerationAndBindings(): void
    {
        $q = $this->db->table('users')->select('id', 'name')->where('age', '>', 18)->where(fn ($q) => $q->where('role', 'admin')->orWhere('role', 'user'))
            ->whereIn('id', [1, 2])->orderBy('name', 'desc')->limit(5)->offset(2);
        $this->assertSame(
            'SELECT "id", "name" FROM "users" WHERE "age" > ? AND ("role" = ? OR "role" = ?) AND "id" IN (?, ?) ORDER BY "name" DESC LIMIT 5 OFFSET 2',
            $q->toSql()
        );
        $this->assertSame([18, 'admin', 'user', 1, 2], $q->getBindings());
    }

    public function testMysqlQuoting(): void
    {
        $g = new \Naluz\Database\Query\Grammar('mysql');
        $this->assertSame('`users`.`id` AS `uid`', $g->wrap('users.id as uid'));
    }

    public function testReading(): void
    {
        $this->assertCount(3, $this->db->table('users')->get());
        $this->assertSame('Ann', $this->db->table('users')->find(1)['name']);
        $this->assertNull($this->db->table('users')->find(99));
        $this->assertSame('Bob', $this->db->table('users')->where('email', 'bob@x.io')->value('name'));
        $this->assertSame(['Ann', 'Bob', 'Cy'], $this->db->table('users')->orderBy('id')->pluck('name')->all());
        $this->assertSame(['ann@x.io' => 'Ann'], $this->db->table('users')->where('id', 1)->pluck('name', 'email')->all());
        $this->assertTrue($this->db->table('users')->where('role', 'admin')->exists());
        $this->assertFalse($this->db->table('users')->where('role', 'ghost')->exists());
    }

    public function testNullHandling(): void
    {
        $this->assertSame('Cy', $this->db->table('users')->where('age', null)->first()['name']);
        $this->assertCount(2, $this->db->table('users')->whereNotNull('age')->get());
        $this->assertCount(2, $this->db->table('users')->where('age', '!=', null)->get());
    }

    public function testAggregates(): void
    {
        $this->assertSame(3, $this->db->table('users')->count());
        $this->assertSame(35.5, $this->db->table('orders')->sum('total'));
        $this->assertSame(50, $this->db->table('users')->sum('age'));
        $this->assertSame(20, $this->db->table('users')->min('age'));
        $this->assertSame(30, $this->db->table('users')->max('age'));
        $this->assertEquals(25, $this->db->table('users')->avg('age'));
    }

    public function testJoinGroupHaving(): void
    {
        $rows = $this->db->table('users')
            ->join('orders', 'users.id', '=', 'orders.user_id')
            ->select('users.name', 'orders.total')
            ->orderBy('orders.total')->get();
        $this->assertSame(['Bob', 'Ann', 'Ann'], $rows->pluck('name')->all());

        $grouped = $this->db->table('orders')->selectRaw('user_id, COUNT(*) AS n')->groupBy('user_id')->having('n', '>', 1)->get();
        $this->assertCount(1, $grouped);
        $this->assertSame(2, $grouped->first()['n']);

        $left = $this->db->table('users')->leftJoin('orders', 'users.id', '=', 'orders.user_id')->whereNull('orders.id')->get();
        $this->assertSame('Cy', $left->first()['name']);
    }

    public function testBetweenExistsAndSubquery(): void
    {
        $this->assertCount(2, $this->db->table('users')->whereBetween('age', [10, 40])->get());
        $withOrders = $this->db->table('users')->whereExists(fn ($q) => $q->from('orders')->whereColumn('orders.user_id', '=', 'users.id'))->get();
        $this->assertSame(['Ann', 'Bob'], $withOrders->pluck('name')->all());
        $sub = $this->db->table('orders')->select('user_id')->where('total', '>', 15);
        $this->assertSame(['Ann'], $this->db->table('users')->whereIn('id', $sub)->pluck('name')->all());
    }

    public function testEmptyWhereInIsFalseNotInvalidSql(): void
    {
        $this->assertCount(0, $this->db->table('users')->whereIn('id', [])->get());
        $this->assertCount(3, $this->db->table('users')->whereNotIn('id', [])->get());
    }

    public function testWhenAndArrayWhere(): void
    {
        $this->assertCount(1, $this->db->table('users')->when(true, fn ($q) => $q->where('name', 'Ann'))->get());
        $this->assertCount(3, $this->db->table('users')->when(false, fn ($q) => $q->where('name', 'Ann'))->get());
        $this->assertCount(1, $this->db->table('users')->where(['role' => 'user', 'name' => 'Bob'])->get());
    }

    public function testWriteOperations(): void
    {
        $id = $this->db->table('users')->insertGetId(['name' => 'Di', 'email' => 'di@x.io']);
        $this->assertSame(4, $id);
        $this->assertSame(1, $this->db->table('users')->where('id', $id)->update(['name' => 'Dee']));
        $this->assertSame('Dee', $this->db->table('users')->find($id)['name']);
        $this->db->table('users')->where('id', 1)->increment('age', 5);
        $this->assertSame(35, $this->db->table('users')->find(1)['age']);
        $this->db->table('users')->where('id', 1)->decrement('age');
        $this->assertSame(34, $this->db->table('users')->find(1)['age']);
        $this->assertSame(1, $this->db->table('users')->where('id', $id)->delete());
        $this->assertSame(3, $this->db->table('users')->count());
    }

    public function testUpsert(): void
    {
        $this->db->table('users')->upsert(
            [['name' => 'Ann 2', 'email' => 'ann@x.io', 'age' => 31], ['name' => 'New', 'email' => 'new@x.io', 'age' => 1]],
            ['email'],
            ['name', 'age']
        );
        $this->assertSame(4, $this->db->table('users')->count());
        $this->assertSame('Ann 2', $this->db->table('users')->where('email', 'ann@x.io')->value('name'));
    }

    public function testBulkInsertRequiresConsistentColumns(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->db->table('users')->insert([['name' => 'a', 'email' => 'a@x'], ['name' => 'b']]);
    }

    public function testPaginate(): void
    {
        $p = $this->db->table('users')->orderBy('id')->paginate(2, 2);
        $this->assertInstanceOf(Paginator::class, $p);
        $this->assertSame(['Cy'], $p->items->pluck('name')->all());
        $json = $p->jsonSerialize();
        $this->assertSame(3, $json['meta']['total']);
        $this->assertSame(2, $json['meta']['last_page']);
        $this->assertFalse($p->hasMorePages());
        $this->assertSame(1000, $this->db->table('users')->paginate(99999)->perPage); // per-page capped
    }

    public function testChunkAndCursor(): void
    {
        $seen = [];
        $this->db->table('users')->orderBy('id')->chunk(2, function ($rows, $page) use (&$seen) {
            $seen[$page] = $rows->pluck('name')->all();
        });
        $this->assertSame([1 => ['Ann', 'Bob'], 2 => ['Cy']], $seen);
        $names = [];
        foreach ($this->db->table('users')->orderBy('id')->cursor() as $row) {
            $names[] = $row['name'];
        }
        $this->assertSame(['Ann', 'Bob', 'Cy'], $names);
    }

    public function testTransactionsCommitRollbackAndNesting(): void
    {
        $this->db->transaction(fn () => $this->db->table('users')->insert(['name' => 'T1', 'email' => 't1@x']));
        $this->assertSame(4, $this->db->table('users')->count());

        try {
            $this->db->transaction(function () {
                $this->db->table('users')->insert(['name' => 'T2', 'email' => 't2@x']);
                throw new \RuntimeException('boom');
            });
        } catch (\RuntimeException) {
        }
        $this->assertSame(4, $this->db->table('users')->count());

        $this->db->transaction(function () {
            $this->db->table('users')->insert(['name' => 'Outer', 'email' => 'o@x']);
            try {
                $this->db->transaction(function () {
                    $this->db->table('users')->insert(['name' => 'Inner', 'email' => 'i@x']);
                    throw new \RuntimeException('inner fails');
                });
            } catch (\RuntimeException) {
            }
        });
        $this->assertSame(['Outer'], $this->db->table('users')->whereIn('name', ['Outer', 'Inner'])->pluck('name')->all());
        $this->assertSame(0, $this->db->transactionLevel());
    }

    public function testForeignKeyCascade(): void
    {
        $this->db->table('users')->where('id', 1)->delete();
        $this->assertSame(1, $this->db->table('orders')->count());
    }

    public function testQueryExceptionHidesBindings(): void
    {
        try {
            $this->db->table('users')->insert(['name' => 'dup', 'email' => 'ann@x.io', 'role' => 'SECRET-VALUE']);
            $this->fail('expected unique violation');
        } catch (QueryException $e) {
            $this->assertStringNotContainsString('SECRET-VALUE', $e->getMessage());
            $this->assertStringContainsString('INSERT INTO', $e->getMessage());
        }
    }

    // ------------------------------------------------------------ SQL injection resistance

    public function testValuesAreNeverInterpolated(): void
    {
        $evil = "x' OR '1'='1";
        $this->assertCount(0, $this->db->table('users')->where('name', $evil)->get());
        $this->assertCount(0, $this->db->table('users')->whereIn('name', [$evil, "'; DROP TABLE users; --"])->get());
        $this->assertCount(0, $this->db->table('users')->where('email', 'like', $evil)->get());
        $this->db->table('users')->insert(['name' => "Robert'); DROP TABLE users;--", 'email' => 'bobby@x']);
        $this->assertSame(4, $this->db->table('users')->count());
        $this->assertSame("Robert'); DROP TABLE users;--", $this->db->table('users')->where('email', 'bobby@x')->value('name'));
    }

    #[DataProvider('injectedIdentifiers')]
    public function testIdentifiersFromUserInputAreRejected(string $payload): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->db->table('users')->where($payload, '=', 1)->get();
    }

    public static function injectedIdentifiers(): array
    {
        return [["id; DROP TABLE users"], ["id) OR (1=1"], ['name`--'], ["a' OR 1=1 --"], ['id /* x */'], ['']];
    }

    public function testOrderByIdentifierAndDirectionAreValidated(): void
    {
        foreach ([fn () => $this->db->table('users')->orderBy('id; DROP TABLE users'), fn () => $this->db->table('users')->orderBy('id', 'asc; DROP TABLE users')] as $attempt) {
            try {
                $attempt()->get();
                $this->fail('injection accepted');
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame(3, $this->db->table('users')->count());
    }

    public function testOperatorsAreWhitelisted(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->db->table('users')->where('id', '= 1 OR 1=1 --', 1)->get();
    }

    public function testRawFragmentsStillSupportBindings(): void
    {
        $rows = $this->db->table('users')->whereRaw('LOWER(name) = ?', ['ann'])->get();
        $this->assertCount(1, $rows);
    }

    public function testIncrementRejectsNonNumeric(): void
    {
        $this->expectException(\TypeError::class);
        $this->db->table('users')->increment('age', '1; DROP TABLE users');
    }
}
