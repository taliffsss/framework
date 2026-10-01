<?php

declare(strict_types=1);

namespace Naluz\Tests\Database;

use Naluz\Database\Connection;
use Naluz\Database\DatabaseManager;
use Naluz\Database\Query\Grammar;
use Naluz\Database\Schema\Schema;
use PHPUnit\Framework\TestCase;

/** A PDO that only records what it is asked to run (no driver or server needed). */
final class RecordingPdo extends \PDO
{
    /** @var list<string> */
    public array $log = [];

    public function __construct()
    {
    }

    public function exec(string $statement): int|false
    {
        $this->log[] = $statement;
        return 0;
    }

    public function beginTransaction(): bool
    {
        $this->log[] = 'BEGIN TRANSACTION';
        return true;
    }

    public function commit(): bool
    {
        $this->log[] = 'COMMIT';
        return true;
    }

    public function rollBack(): bool
    {
        $this->log[] = 'ROLLBACK';
        return true;
    }
}

/** A "SQL Server" connection that records statements instead of sending them. */
final class RecordingSqlServer extends Connection
{
    /** @var list<array{0:string,1:array}> */
    public array $statements = [];
    /** @var list<array<string,mixed>> rows returned by select() */
    public array $rows = [];

    public function __construct(public RecordingPdo $recording = new RecordingPdo())
    {
        parent::__construct($recording, 'sqlsrv');
    }

    public function statement(string $sql, array $bindings = []): bool
    {
        $this->statements[] = [$sql, $bindings];
        return true;
    }

    public function affecting(string $sql, array $bindings = []): int
    {
        $this->statements[] = [$sql, $bindings];
        return 1;
    }

    public function select(string $sql, array $bindings = [], bool $useWritePdo = false): array
    {
        $this->statements[] = [$sql, $bindings];
        return $this->rows;
    }

    public function sql(): array
    {
        return array_column($this->statements, 0);
    }
}

/**
 * Microsoft SQL Server. The SQL it needs is generated and pinned here; there is no SQL Server in the test environment,
 * so these tests prove the dialect rules, not a live round trip (see docs/database.md).
 */
final class SqlServerTest extends TestCase
{
    private function db(): RecordingSqlServer
    {
        return new RecordingSqlServer();
    }

    // ---------------------------------------------------------------- identifiers

    public function testIdentifiersUseSquareBrackets(): void
    {
        $g = new Grammar('sqlsrv');
        $this->assertSame('[users]', $g->wrap('users'));
        $this->assertSame('[users].[id]', $g->wrap('users.id'));
        $this->assertSame('[users].*', $g->wrap('users.*'));
        $this->assertSame('[users].[id] AS [uid]', $g->wrap('users.id as uid'));
        $this->assertSame('[we]]ird]', $g->quote('we]ird'), 'a closing bracket is escaped by doubling it');
    }

    public function testInjectionThroughIdentifiersStillThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->db()->table('users')->where('id]; DROP TABLE users; --', '=', 1)->toSql();
    }

    // ---------------------------------------------------------------- reading

    public function testSelectWithJoinsGroupingAndOrder(): void
    {
        $sql = $this->db()->table('orders')
            ->join('users', 'users.id', '=', 'orders.user_id')
            ->select('users.name', 'orders.total')
            ->where('orders.total', '>', 100)
            ->whereIn('users.role', ['a', 'b'])
            ->groupBy('users.name', 'orders.total')
            ->orderBy('orders.total', 'desc')
            ->toSql();
        $this->assertSame(
            'SELECT [users].[name], [orders].[total] FROM [orders] INNER JOIN [users] ON [users].[id] = [orders].[user_id] '
            . 'WHERE [orders].[total] > ? AND [users].[role] IN (?, ?) GROUP BY [users].[name], [orders].[total] ORDER BY [orders].[total] DESC',
            $sql
        );
    }

    public function testLimitAndOffsetUseOffsetFetchWithAnOrderBy(): void
    {
        $q = fn () => $this->db()->table('users');
        $this->assertSame('SELECT * FROM [users] ORDER BY [id] ASC OFFSET 0 ROWS FETCH NEXT 10 ROWS ONLY', $q()->orderBy('id')->limit(10)->toSql());
        $this->assertSame('SELECT * FROM [users] ORDER BY [id] DESC OFFSET 20 ROWS FETCH NEXT 10 ROWS ONLY', $q()->orderBy('id', 'desc')->limit(10)->offset(20)->toSql());
        $this->assertSame('SELECT * FROM [users] ORDER BY [id] ASC OFFSET 5 ROWS', $q()->orderBy('id')->offset(5)->toSql(), 'offset without limit');
    }

    public function testPagingWithoutAnOrderByGetsAHarmlessOne(): void
    {
        $q = $this->db()->table('users');
        $this->assertSame('SELECT * FROM [users] ORDER BY (SELECT 0) OFFSET 0 ROWS FETCH NEXT 1 ROWS ONLY', $q->limit(1)->toSql());
        $this->assertSame('SELECT * FROM [users]', $this->db()->table('users')->toSql(), 'no paging, no ORDER BY');
    }

    public function testLimitZeroUsesTop(): void
    {
        $this->assertSame('SELECT TOP (0) * FROM [users]', $this->db()->table('users')->limit(0)->toSql());
        $this->assertSame('SELECT DISTINCT TOP (0) [a] FROM [users]', $this->db()->table('users')->select('a')->distinct()->limit(0)->toSql());
    }

    public function testAggregatesAndExistsAndFirstPlans(): void
    {
        $q = fn () => $this->db()->table('users')->where('age', '>', 1)->orderBy('id')->limit(5);
        $this->assertSame('SELECT COUNT(*) AS [aggregate] FROM [users] WHERE [age] > ?', $q()->readPlan('count')[0]);
        $this->assertSame('SELECT SUM([age]) AS [aggregate] FROM [users] WHERE [age] > ?', $q()->readPlan('sum', ['age'])[0]);
        $this->assertSame(
            'SELECT 1 AS one FROM [users] WHERE [age] > ? ORDER BY [id] ASC OFFSET 0 ROWS FETCH NEXT 1 ROWS ONLY',
            $q()->readPlan('exists')[0]
        );
    }

    public function testSubqueriesAndExists(): void
    {
        $sql = $this->db()->table('users')
            ->whereExists(fn ($q) => $q->from('orders')->whereColumn('orders.user_id', '=', 'users.id'))
            ->toSql();
        $this->assertSame('SELECT * FROM [users] WHERE EXISTS (SELECT * FROM [orders] WHERE [orders].[user_id] = [users].[id])', $sql);
    }

    // ---------------------------------------------------------------- writing

    public function testInsertUpdateDeleteTruncate(): void
    {
        $db = $this->db();
        $db->table('users')->insert(['name' => 'A', 'age' => 1]);
        $db->table('users')->insert([['name' => 'A'], ['name' => 'B']]);
        $db->table('users')->where('id', 1)->update(['name' => 'Z']);
        $db->table('users')->where('id', 1)->increment('age', 2);
        $db->table('users')->where('id', 1)->delete();
        $db->table('users')->truncate();
        $this->assertSame([
            'INSERT INTO [users] ([name], [age]) VALUES (?, ?)',
            'INSERT INTO [users] ([name]) VALUES (?), (?)',
            'UPDATE [users] SET [name] = ? WHERE [id] = ?',
            'UPDATE [users] SET [age] = [age] + 2 WHERE [id] = ?',
            'DELETE FROM [users] WHERE [id] = ?',
            'TRUNCATE TABLE [users]',
        ], $db->sql());
    }

    public function testUpsertIsAMerge(): void
    {
        $db = $this->db();
        $db->table('users')->upsert([['email' => 'a@x', 'name' => 'A'], ['email' => 'b@x', 'name' => 'B']], ['email'], ['name']);
        $this->assertSame(
            'MERGE [users] AS target USING (VALUES (?, ?), (?, ?)) AS source ([email], [name]) ON target.[email] = source.[email] '
            . 'WHEN MATCHED THEN UPDATE SET target.[name] = source.[name] '
            . 'WHEN NOT MATCHED THEN INSERT ([email], [name]) VALUES (source.[email], source.[name]);',
            $db->sql()[0]
        );
        $this->assertSame(['a@x', 'A', 'b@x', 'B'], $db->statements[0][1]);
    }

    public function testUpsertWithCompositeKeyAndNothingToUpdate(): void
    {
        $db = $this->db();
        $db->table('t')->upsert([['a' => 1, 'b' => 2]], ['a', 'b'], []);
        $this->assertSame(
            'MERGE [t] AS target USING (VALUES (?, ?)) AS source ([a], [b]) ON target.[a] = source.[a] AND target.[b] = source.[b] '
            . 'WHEN NOT MATCHED THEN INSERT ([a], [b]) VALUES (source.[a], source.[b]);',
            $db->sql()[0]
        );
    }

    public function testInsertIgnoreIsRefusedInsteadOfSilentlyWrong(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->db()->table('users')->insert(['a' => 1], ignore: true);
    }

    public function testBulkInsertsAreChunkedToSqlServerLimits(): void
    {
        $db = $this->db();
        $rows = array_map(fn ($i) => ['a' => $i, 'b' => $i, 'c' => $i], range(1, 1500)); // 4500 parameters
        $db->table('t')->insert($rows);
        $this->assertGreaterThan(1, count($db->statements));
        $total = 0;
        foreach ($db->statements as [$sql, $bindings]) {
            $this->assertLessThanOrEqual(2000, count($bindings), 'under the 2100-parameter limit');
            $this->assertLessThanOrEqual(1000, substr_count($sql, '(?, ?, ?)'), 'under the 1000-row limit');
            $total += intdiv(count($bindings), 3);
        }
        $this->assertSame(1500, $total, 'every row was inserted exactly once');
    }

    public function testNarrowBulkInsertsAreCappedAt1000Rows(): void
    {
        $db = $this->db();
        $db->table('t')->insert(array_map(fn ($i) => ['a' => $i], range(1, 2500)));
        $this->assertSame([1000, 1000, 500], array_map(fn ($s) => count($s[1]), $db->statements));
    }

    public function testBulkUpsertIsChunkedToo(): void
    {
        $db = $this->db();
        $db->table('t')->upsert(array_map(fn ($i) => ['k' => $i, 'v' => $i], range(1, 2000)), ['k'], ['v']);
        $this->assertGreaterThan(1, count($db->statements));
        foreach ($db->statements as [$sql, $bindings]) {
            $this->assertLessThanOrEqual(2000, count($bindings));
            $this->assertStringStartsWith('MERGE', $sql);
        }
    }

    public function testOtherDriversStillSendOneStatement(): void
    {
        $sqlite = new class extends Connection {
            public array $sent = [];

            public function __construct()
            {
                parent::__construct(new RecordingPdo(), 'sqlite');
            }

            public function statement(string $sql, array $bindings = []): bool
            {
                $this->sent[] = $sql;
                return true;
            }
        };
        $sqlite->table('t')->insert(array_map(fn ($i) => ['a' => $i], range(1, 1500)));
        $this->assertCount(1, $sqlite->sent);
    }

    public function testHugeWhereInListsFailWithAHelpfulMessage(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('2100 parameters');
        $this->db()->table('t')->whereIn('id', range(1, 2500));
    }

    // ---------------------------------------------------------------- schema

    public function testCreateTableWithEveryColumnType(): void
    {
        $db = $this->db();
        (new Schema($db))->create('things', function ($t) {
            $t->id();
            $t->string('name');
            $t->string('long_text_col', 5000)->nullable();
            $t->text('body');
            $t->integer('qty')->default(5);
            $t->bigInteger('big')->unsigned();
            $t->boolean('active')->default(true);
            $t->decimal('price', 8, 2);
            $t->float('ratio');
            $t->json('meta')->nullable();
            $t->date('born');
            $t->timestamp('seen_at')->nullable();
            $t->string('label')->default("O'Brien");
            $t->timestamp('created')->useCurrent();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->unique('name');
            $t->index(['qty', 'active']);
        });
        $this->assertSame(
            'CREATE TABLE [things] ([id] BIGINT IDENTITY(1,1) PRIMARY KEY, [name] NVARCHAR(255) NOT NULL, '
            . '[long_text_col] NVARCHAR(MAX) NULL, [body] NVARCHAR(MAX) NOT NULL, [qty] INT NOT NULL DEFAULT 5, '
            . '[big] BIGINT NOT NULL, [active] BIT NOT NULL DEFAULT 1, [price] DECIMAL(8, 2) NOT NULL, [ratio] FLOAT NOT NULL, '
            . '[meta] NVARCHAR(MAX) NULL, [born] DATE NOT NULL, [seen_at] DATETIME2(0) NULL, '
            . "[label] NVARCHAR(255) NOT NULL DEFAULT N'O''Brien', [created] DATETIME2(0) NOT NULL DEFAULT CURRENT_TIMESTAMP, "
            . '[user_id] BIGINT NOT NULL, FOREIGN KEY ([user_id]) REFERENCES [users] ([id]) ON DELETE CASCADE)',
            $db->sql()[0]
        );
        $this->assertContains('CREATE UNIQUE INDEX [things_name_unique] ON [things] ([name])', $db->sql());
        $this->assertContains('CREATE INDEX [things_qty_active_index] ON [things] ([qty], [active])', $db->sql());
        $this->assertContains('CREATE INDEX [things_user_id_index] ON [things] ([user_id])', $db->sql());
        $this->assertStringNotContainsString('CHARACTER SET', $db->sql()[0]);
    }

    public function testAlterTableUsesSqlServerSyntax(): void
    {
        $db = $this->db();
        (new Schema($db))->table('things', function ($t) {
            $t->string('extra')->nullable();
            $t->dropColumn('old');
        });
        $this->assertSame('ALTER TABLE [things] ADD [extra] NVARCHAR(255) NULL', $db->sql()[0], 'no COLUMN keyword');
        $this->assertSame('ALTER TABLE [things] DROP COLUMN [old]', $db->sql()[1]);
    }

    public function testDropAndHasTable(): void
    {
        $db = $this->db();
        $schema = new Schema($db);
        $schema->dropIfExists('things');
        $schema->drop('things');
        $db->rows = [['present' => 1]];
        $this->assertTrue($schema->hasTable('things'));
        $db->rows = [];
        $this->assertFalse($schema->hasTable('things'));
        $this->assertSame('DROP TABLE IF EXISTS [things]', $db->sql()[0]);
        $this->assertSame('DROP TABLE [things]', $db->sql()[1]);
        $this->assertStringContainsString('INFORMATION_SCHEMA.TABLES', $db->sql()[2]);
        $this->assertSame(['things'], $db->statements[2][1]);
    }

    public function testDropAllTablesDropsForeignKeysFirst(): void
    {
        $db = $this->db();
        $db->rows = [['stmt' => 'ALTER TABLE [dbo].[posts] DROP CONSTRAINT [fk1]', 'name' => 'posts']];
        (new Schema($db))->dropAllTables();
        $sql = $db->sql();
        $this->assertStringContainsString('sys.foreign_keys', $sql[0]);
        $this->assertSame('ALTER TABLE [dbo].[posts] DROP CONSTRAINT [fk1]', $sql[1]);
        $this->assertStringContainsString("TABLE_TYPE = 'BASE TABLE'", $sql[2]);
        $this->assertSame('DROP TABLE IF EXISTS [posts]', $sql[3]);
    }

    // ---------------------------------------------------------------- transactions

    public function testNestedTransactionsUseSaveTransaction(): void
    {
        $db = $this->db();
        $db->transaction(function () use ($db) {
            try {
                $db->transaction(function () {
                    throw new \RuntimeException('inner');
                });
            } catch (\RuntimeException) {
            }
            $db->transaction(fn () => null);
        });
        $this->assertSame([
            'BEGIN TRANSACTION',
            'SAVE TRANSACTION naluz_1',
            'ROLLBACK TRANSACTION naluz_1',
            'SAVE TRANSACTION naluz_1',      // a successful inner transaction: SQL Server has no RELEASE statement
            'COMMIT',
        ], $db->recording->log);
    }

    public function testDdlIsTransactionalOnSqlServer(): void
    {
        $this->assertTrue($this->db()->transactionalDdl());
    }

    // ---------------------------------------------------------------- connecting

    public function testDsnBuildsEncryptedByDefault(): void
    {
        $dsn = DatabaseManager::sqlsrvDsn(['host' => 'db.example.com', 'port' => 1433, 'database' => 'app']);
        $this->assertSame('sqlsrv:Server=db.example.com,1433;Database=app;Encrypt=yes;TrustServerCertificate=no', $dsn);
    }

    public function testDsnOptionsAndReadIntent(): void
    {
        $dsn = DatabaseManager::sqlsrvDsn(['host' => 'h', 'port' => '1434', 'database' => 'd', 'encrypt' => false, 'trust_server_certificate' => true], true);
        $this->assertSame('sqlsrv:Server=h,1434;Database=d;Encrypt=no;TrustServerCertificate=yes;ApplicationIntent=ReadOnly', $dsn);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('hostile')]
    public function testDsnRejectsInjection(array $config): void
    {
        $this->expectException(\InvalidArgumentException::class);
        DatabaseManager::sqlsrvDsn($config);
    }

    public static function hostile(): array
    {
        return [
            [['host' => 'h;TrustServerCertificate=yes', 'database' => 'd']],
            [['host' => 'h', 'database' => 'd;Encrypt=no']],
            [['host' => 'h}', 'database' => 'd']],
            [['host' => '', 'database' => 'd']],
        ];
    }

    public function testReadWriteSplitForSqlServer(): void
    {
        $out = DatabaseManager::expand([
            'driver' => 'sqlsrv', 'database' => 'app', 'username' => 'sa', 'port' => 1433,
            'write' => ['host' => 'primary.db'], 'read' => ['host' => ['replica1.db', 'replica2.db']],
        ]);
        $this->assertSame('primary.db', $out['write']['host']);
        $this->assertSame(['replica1.db', 'replica2.db'], array_column($out['reads'], 'host'));
        $this->assertStringContainsString('ApplicationIntent=ReadOnly', DatabaseManager::sqlsrvDsn($out['reads'][0], true));
    }

    public function testConfigShipsASqlServerConnection(): void
    {
        $cfg = require dirname(__DIR__, 2) . '/config/database.php';
        $this->assertSame('sqlsrv', $cfg['connections']['sqlsrv']['driver']);
        $this->assertArrayHasKey('sticky', $cfg['connections']['sqlsrv']);
    }

    public function testUsingTheDriverWithoutTheExtensionGivesAClearError(): void
    {
        if (extension_loaded('pdo_sqlsrv')) {
            $this->markTestSkipped('pdo_sqlsrv is installed.');
        }
        $c = (new DatabaseManager(['default' => 'm', 'connections' => ['m' => ['driver' => 'sqlsrv', 'host' => 'localhost', 'database' => 'x']]]))->connection();
        $this->expectException(\PDOException::class);
        $c->pdo();
    }
}
