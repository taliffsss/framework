<?php

declare(strict_types=1);

namespace Naluz\Tests\Database;

use Naluz\Database\Connection;
use Naluz\Database\DatabaseManager;
use Naluz\Database\QueryException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Read/write splitting, tested with real connections: SQLite files stand in for the primary and for replicas, and each
 * holds a marker row so a query's result proves WHICH server answered it.
 */
final class ReadWriteSplitTest extends TestCase
{
    private string $dir;
    private string $errlog;
    /** @var false|string */
    private $oldErrLog;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/naluz-rw-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
        $this->errlog = tempnam(sys_get_temp_dir(), 'errlog');
        $this->oldErrLog = ini_set('error_log', $this->errlog);
    }

    protected function tearDown(): void
    {
        ini_set('error_log', (string) $this->oldErrLog);
        @unlink($this->errlog);
        foreach (glob($this->dir . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->dir);
    }

    private function seed(string $name, string $marker): string
    {
        $file = $this->dir . '/' . $name . '.sqlite';
        $pdo = new \PDO('sqlite:' . $file);
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');
        $pdo->prepare('INSERT INTO users (name) VALUES (?)')->execute([$marker]);
        return $file;
    }

    private function connection(array $extra = [], ?array $replicas = null): Connection
    {
        $primary = $this->seed('primary', 'from-primary');
        $replica = $this->seed('replica', 'from-replica');
        return (new DatabaseManager(['default' => 'db', 'connections' => ['db' => [
            'driver' => 'sqlite', 'database' => $primary,
            'read' => $replicas ?? [['database' => $replica]],
        ] + $extra]]))->connection();
    }

    private function names(Connection $c, bool $write = false): array
    {
        return array_column($c->select('SELECT name FROM users ORDER BY id', [], $write), 'name');
    }

    // ---------------------------------------------------------------- routing

    public function testReadsGoToTheReplicaAndWritesToThePrimary(): void
    {
        $c = $this->connection();
        $this->assertTrue($c->hasReadConnection());
        $this->assertSame(['from-replica'], $this->names($c), 'a plain SELECT is answered by the replica');

        $c->table('users')->insert(['name' => 'written']);
        $primary = new \PDO('sqlite:' . $this->dir . '/primary.sqlite');
        $replica = new \PDO('sqlite:' . $this->dir . '/replica.sqlite');
        $this->assertSame(2, (int) $primary->query('SELECT COUNT(*) FROM users')->fetchColumn(), 'the INSERT landed on the primary');
        $this->assertSame(1, (int) $replica->query('SELECT COUNT(*) FROM users')->fetchColumn(), 'and never on the replica');
    }

    public function testStickyReadsSeeTheirOwnWritesDespiteReplicaLag(): void
    {
        $c = $this->connection(['sticky' => true]);
        $this->assertSame(['from-replica'], $this->names($c));
        $c->table('users')->insert(['name' => 'mine']);
        $this->assertSame(['from-primary', 'mine'], $this->names($c), 'after a write, reads come from the primary');
    }

    public function testWithoutStickyReadsStayOnTheReplica(): void
    {
        $c = $this->connection(['sticky' => false]);
        $c->table('users')->insert(['name' => 'mine']);
        $this->assertSame(['from-replica'], $this->names($c), 'stale by design: the replica has not received the write');
    }

    public function testTransactionsReadFromThePrimary(): void
    {
        $c = $this->connection(['sticky' => false]);
        $c->transaction(function (Connection $c) {
            $this->assertSame(['from-primary'], $this->names($c), 'inside a transaction even the first read uses the primary');
            $c->table('users')->insert(['name' => 'tx']);
            $this->assertSame(['from-primary', 'tx'], $this->names($c), 'and sees the transaction\'s own uncommitted rows');
        });
        $this->assertSame(['from-replica'], $this->names($c), 'after the transaction, reads go back to the replica');
    }

    public function testRolledBackTransactionLeavesNothingBehind(): void
    {
        $c = $this->connection(['sticky' => false]);
        try {
            $c->transaction(function (Connection $c) {
                $c->table('users')->insert(['name' => 'gone']);
                throw new \RuntimeException('rollback');
            });
        } catch (\RuntimeException) {
        }
        $this->assertSame(['from-primary'], $this->names($c, true));
    }

    public function testExplicitPrimaryReads(): void
    {
        $c = $this->connection(['sticky' => false]);
        $this->assertSame(['from-primary'], $c->table('users')->useWritePdo()->pluck('name')->all());
        $this->assertSame(['from-primary'], $this->names($c, true));
        $this->assertSame('from-primary', $c->table('users')->useWritePdo()->first()['name']);
        $this->assertSame(1, $c->table('users')->useWritePdo()->count());
        $this->assertSame(['from-primary'], $c->usingWritePdo(fn (Connection $c) => $this->names($c)));
        $this->assertSame(['from-replica'], $this->names($c), 'the block ended: normal routing resumes');

        $viaCursor = [];
        foreach ($c->table('users')->useWritePdo()->cursor() as $row) {
            $viaCursor[] = $row['name'];
        }
        $this->assertSame(['from-primary'], $viaCursor);
    }

    public function testWriteStatementsIssuedThroughSelectAreRoutedToThePrimary(): void
    {
        $c = $this->connection(['sticky' => false]);
        // INSERT … RETURNING is executed with select() so the returned row can be fetched; it is still a write
        $row = $c->selectOne("INSERT INTO users (name) VALUES ('returning') RETURNING id, name");
        $this->assertSame('returning', $row['name']);
        $primary = new \PDO('sqlite:' . $this->dir . '/primary.sqlite');
        $this->assertSame(2, (int) $primary->query('SELECT COUNT(*) FROM users')->fetchColumn());
    }

    #[DataProvider('statementsThatNeedThePrimary')]
    public function testLockingAndWriteStatementsAreDetected(string $sql, bool $needsPrimary): void
    {
        $this->assertSame($needsPrimary, Connection::requiresWrite($sql));
    }

    public static function statementsThatNeedThePrimary(): array
    {
        return [
            ['SELECT * FROM users', false], ['  select 1', false], ['WITH x AS (SELECT 1) SELECT * FROM x', false],
            ['SELECT * FROM users WHERE id = 1 FOR UPDATE', true], ['SELECT * FROM users FOR SHARE', true],
            ['SELECT * FROM users LOCK IN SHARE MODE', true], ['SELECT * FROM users FOR NO KEY UPDATE', true],
            ['SELECT * FROM users WITH (UPDLOCK)', true], ['SELECT * FROM users WITH (HOLDLOCK, ROWLOCK)', true],
            ['INSERT INTO t VALUES (1) RETURNING id', true], ['UPDATE t SET a = 1', true], ['DELETE FROM t', true],
            ['WITH c AS (SELECT 1) INSERT INTO t SELECT * FROM c', true], ['SELECT * FROM forum_updates', false],
        ];
    }

    // ---------------------------------------------------------------- isolation

    public function testTheReadConnectionIsReadOnlyAndCannotCollideWithWrites(): void
    {
        $c = $this->connection();
        $read = $c->readPdo();
        $write = $c->pdo();
        $this->assertNotSame($read, $write, 'two independent sessions');

        $this->expectException(\PDOException::class);
        try {
            $read->exec("INSERT INTO users (name) VALUES ('sneaky')");
        } finally {
            $this->assertSame(['from-replica'], array_column($read->query('SELECT name FROM users')->fetchAll(\PDO::FETCH_ASSOC), 'name'));
        }
    }

    public function testReadOnlyEnforcementCanBeDisabled(): void
    {
        $c = $this->connection(['read_only' => false]);
        $c->readPdo()->exec("INSERT INTO users (name) VALUES ('allowed')");
        $this->addToAssertionCount(1);
    }

    public function testAWriteTransactionDoesNotBlockOrLeakIntoReadSessions(): void
    {
        $c = $this->connection(['sticky' => false]);
        $c->beginTransaction();
        $c->table('users')->insert(['name' => 'pending']);
        // a different session (the replica connection) is unaffected by the open write transaction
        $replicaRows = $c->readPdo()->query('SELECT name FROM users')->fetchAll(\PDO::FETCH_COLUMN);
        $this->assertSame(['from-replica'], $replicaRows);
        $c->rollBack();
        $this->assertSame(0, $c->transactionLevel());
    }

    public function testLastInsertIdComesFromTheWriteConnection(): void
    {
        $c = $this->connection();
        $id = $c->table('users')->insertGetId(['name' => 'x']);
        $this->assertSame(2, $id);
        $this->assertSame('2', $c->lastInsertId());
    }

    // ---------------------------------------------------------------- replica pools

    public function testFailoverSkipsAnUnreachableReplica(): void
    {
        $c = $this->connection(['read_strategy' => 'ordered'], [
            ['database' => $this->dir . '/no-such-dir/replica.sqlite'],   // cannot be opened
            ['database' => $this->dir . '/replica.sqlite'],
        ]);
        $this->assertSame(['from-replica'], $this->names($c), 'first replica failed, second answered');
    }

    public function testWhenEveryReplicaIsDownReadsFallBackToThePrimary(): void
    {
        $c = $this->connection(['read_strategy' => 'ordered'], [
            ['database' => $this->dir . '/nope-1/r.sqlite'], ['database' => $this->dir . '/nope-2/r.sqlite'],
        ]);
        $this->assertSame(['from-primary'], $this->names($c));
        $this->assertStringContainsString('read replicas', (string) file_get_contents($this->errlog), 'the outage is logged');
        $this->assertSame(['from-primary'], $this->names($c), 'and keeps working');
    }

    public function testFallbackCanBeDisabled(): void
    {
        $c = $this->connection(['read_fallback' => false], [['database' => $this->dir . '/nope/r.sqlite']]);
        $this->expectException(QueryException::class);
        $this->names($c);
    }

    public function testRandomStrategySpreadsReadsOverAllReplicas(): void
    {
        $seen = [];
        $this->seed('primary', 'p');
        $this->seed('r1', 'replica-1');
        $this->seed('r2', 'replica-2');
        for ($i = 0; $i < 40; $i++) {
            $c = (new DatabaseManager(['default' => 'db', 'connections' => ['db' => [
                'driver' => 'sqlite', 'database' => $this->dir . '/primary.sqlite',
                'read' => [['database' => $this->dir . '/r1.sqlite'], ['database' => $this->dir . '/r2.sqlite']],
            ]]]))->connection();
            $seen[$this->names($c)[0]] = true;
        }
        $this->assertSame(['replica-1', 'replica-2'], array_keys($this->sorted($seen)), 'both replicas were used');
    }

    private function sorted(array $a): array
    {
        ksort($a);
        return $a;
    }

    public function testOrderedStrategyAlwaysUsesTheFirstHealthyReplica(): void
    {
        $this->seed('primary', 'p');
        $this->seed('r1', 'replica-1');
        $this->seed('r2', 'replica-2');
        for ($i = 0; $i < 10; $i++) {
            $c = (new DatabaseManager(['default' => 'db', 'connections' => ['db' => [
                'driver' => 'sqlite', 'database' => $this->dir . '/primary.sqlite', 'read_strategy' => 'ordered',
                'read' => [['database' => $this->dir . '/r1.sqlite'], ['database' => $this->dir . '/r2.sqlite']],
            ]]]))->connection();
            $this->assertSame(['replica-1'], $this->names($c));
        }
    }

    public function testInvalidStrategyIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new DatabaseManager(['default' => 'db', 'connections' => ['db' => ['driver' => 'sqlite', 'database' => ':memory:', 'read_strategy' => 'chaos']]]))->connection();
    }

    public function testWithoutReadConfigNothingChanges(): void
    {
        $file = $this->seed('solo', 'solo');
        $c = (new DatabaseManager(['default' => 'db', 'connections' => ['db' => ['driver' => 'sqlite', 'database' => $file]]]))->connection();
        $this->assertFalse($c->hasReadConnection());
        $this->assertSame($c->pdo(), $c->readPdo());
        $this->assertSame(['solo'], $this->names($c));
    }

    // ---------------------------------------------------------------- configuration forms

    public function testExpandHostListsAndInheritance(): void
    {
        $out = DatabaseManager::expand([
            'driver' => 'mysql', 'database' => 'app', 'username' => 'rw', 'password' => 'secret', 'port' => 3306,
            'write' => ['host' => '10.0.0.1'],
            'read' => ['host' => ['10.0.0.2', '10.0.0.3'], 'username' => 'ro'],
        ]);
        $this->assertSame('10.0.0.1', $out['write']['host']);
        $this->assertSame('rw', $out['write']['username']);
        $this->assertSame(['10.0.0.2', '10.0.0.3'], array_column($out['reads'], 'host'));
        $this->assertSame(['ro', 'ro'], array_column($out['reads'], 'username'), 'section overrides');
        $this->assertSame(['secret', 'secret'], array_column($out['reads'], 'password'), 'everything else is inherited');
        $this->assertSame('app', $out['reads'][0]['database']);
    }

    public function testExpandOneArrayPerReplica(): void
    {
        $out = DatabaseManager::expand([
            'driver' => 'mysql', 'host' => 'base', 'database' => 'app',
            'read' => [['host' => 'r1'], ['host' => 'r2', 'port' => 3307]],
        ]);
        $this->assertSame(['r1', 'r2'], array_column($out['reads'], 'host'));
        $this->assertSame([3307], array_values(array_filter(array_column($out['reads'], 'port'))));
        $this->assertSame('base', $out['write']['host'], 'the write side keeps the connection host');
    }

    public function testExpandWithNoReadSectionOrEmptyHostList(): void
    {
        $this->assertSame([], DatabaseManager::expand(['driver' => 'mysql', 'host' => 'h'])['reads']);
        $this->assertSame([], DatabaseManager::expand(['driver' => 'mysql', 'host' => 'h', 'read' => ['host' => []]])['reads']);
    }

    public function testEnvironmentDrivenConfig(): void
    {
        \Naluz\Support\Env::set('DB_READ_HOST', '10.0.0.2, 10.0.0.3');
        \Naluz\Support\Env::set('DB_WRITE_HOST', '10.0.0.1');
        \Naluz\Support\Env::set('DB_READ_PASSWORD', 'readonly-pw');
        try {
            $cfg = require dirname(__DIR__, 2) . '/config/database.php';
            $out = DatabaseManager::expand($cfg['connections']['mysql']);
            $this->assertSame('10.0.0.1', $out['write']['host']);
            $this->assertSame(['10.0.0.2', '10.0.0.3'], array_column($out['reads'], 'host'));
            $this->assertSame('readonly-pw', $out['reads'][0]['password']);
            $this->assertTrue($cfg['connections']['mysql']['sticky']);
        } finally {
            \Naluz\Support\Env::flush();
        }
        $cfg = require dirname(__DIR__, 2) . '/config/database.php';
        $this->assertArrayNotHasKey('read', $cfg['connections']['mysql'], 'no DB_READ_HOST: no splitting');
    }
}
