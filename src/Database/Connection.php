<?php

declare(strict_types=1);

namespace Naluz\Database;

use Naluz\Database\Query\Builder;
use Naluz\Database\Query\Grammar;
use Naluz\Database\Query\Expression;
use Naluz\Database\Schema\Schema;

/**
 * Thin PDO wrapper: always prepared statements, lazy connect, nested transactions
 * (savepoints), query listeners.
 */
class Connection
{
    private ?\PDO $pdo = null;
    private ?\Closure $factory = null;
    private int $transactions = 0;
    /** @var list<callable(string,bool,?int):void> */
    private array $writeListeners = [];
    /** @var list<string> write statements issued inside the current transaction */
    private array $pendingWrites = [];
    /** @var list<array{0:string,1:int}> writes executed but not yet announced to listeners */
    private array $queuedWrites = [];
    /** @var list<callable(string,array,float):void> */
    private array $listeners = [];
    private readonly Grammar $grammar;

    /** @param \PDO|\Closure():\PDO $pdo */
    public function __construct(\PDO|\Closure $pdo, private readonly string $driver, private readonly string $name = 'default')
    {
        if ($pdo instanceof \PDO) {
            $this->pdo = $pdo;
        } else {
            $this->factory = $pdo;
        }
        $this->grammar = new Grammar($driver);
    }

    public function pdo(): \PDO
    {
        if ($this->pdo === null) {
            $this->pdo = ($this->factory)();
        }
        return $this->pdo;
    }

    public function driver(): string
    {
        return $this->driver;
    }

    /** MySQL implicitly commits DDL, so wrapping migrations in a transaction only helps elsewhere. */
    public function transactionalDdl(): bool
    {
        return $this->driver !== 'mysql';
    }

    public function name(): string
    {
        return $this->name;
    }

    public function grammar(): Grammar
    {
        return $this->grammar;
    }

    public function table(string|Expression $table, ?string $as = null): Builder
    {
        return (new Builder($this))->from($table, $as);
    }

    public function query(): Builder
    {
        return new Builder($this);
    }

    public function schema(): Schema
    {
        return new Schema($this);
    }

    public static function raw(string $sql): Expression
    {
        return new Expression($sql);
    }

    /** @return list<array<string,mixed>> */
    public function select(string $sql, array $bindings = []): array
    {
        return $this->run($sql, $bindings, static fn (\PDOStatement $s) => $s->fetchAll(\PDO::FETCH_ASSOC));
    }

    /** @return \Generator<int,array<string,mixed>> */
    public function cursor(string $sql, array $bindings = []): \Generator
    {
        $stmt = $this->prepare($sql, $bindings);
        try {
            while (($row = $stmt->fetch(\PDO::FETCH_ASSOC)) !== false) {
                yield $row;
            }
        } finally {
            $stmt->closeCursor();
        }
    }

    public function selectOne(string $sql, array $bindings = []): ?array
    {
        return $this->select($sql, $bindings)[0] ?? null;
    }

    /** Execute INSERT/UPDATE/DELETE and return affected rows. */
    public function affecting(string $sql, array $bindings = []): int
    {
        return $this->run($sql, $bindings, static fn (\PDOStatement $s) => $s->rowCount());
    }

    /** Execute any statement (DDL etc.). */
    public function statement(string $sql, array $bindings = []): bool
    {
        return $this->run($sql, $bindings, static fn () => true);
    }

    public function lastInsertId(?string $sequence = null): string
    {
        return (string) $this->pdo()->lastInsertId($sequence);
    }

    private function prepare(string $sql, array $bindings): \PDOStatement
    {
        $start = microtime(true);
        try {
            $stmt = $this->pdo()->prepare($sql);
            foreach (array_values($bindings) as $i => $value) {
                $stmt->bindValue($i + 1, $value, match (true) {
                    is_int($value) => \PDO::PARAM_INT,
                    is_bool($value) => \PDO::PARAM_BOOL,
                    $value === null => \PDO::PARAM_NULL,
                    default => \PDO::PARAM_STR,
                });
            }
            $stmt->execute();
        } catch (\PDOException $e) {
            throw new QueryException($sql, $bindings, $e);
        }
        foreach ($this->listeners as $listener) {
            $listener($sql, $bindings, (microtime(true) - $start) * 1000);
        }
        if ($this->writeListeners !== [] && self::isWrite($sql)) {
            // announced after the statement has been fully consumed (see run()), so listeners may run queries safely
            $this->queuedWrites[] = [$sql, $stmt->rowCount()];
        }
        return $stmt;
    }

    /**
     * @template T
     * @param \Closure(\PDOStatement):T $fetch
     * @return T
     */
    private function run(string $sql, array $bindings, \Closure $fetch): mixed
    {
        $stmt = $this->prepare($sql, $bindings);
        try {
            return $fetch($stmt);
        } finally {
            $stmt->closeCursor();
            $this->dispatchWrites();
        }
    }

    /**
     * Be told about every statement that changes data or schema (INSERT/UPDATE/DELETE/DDL…), including ones issued by
     * `Connection::select()` such as PostgreSQL's `INSERT … RETURNING`.
     *
     * The listener receives `(sql, committed, affectedRows)`:
     *  - statements that matched no rows (`affectedRows === 0` for INSERT/UPDATE/DELETE) are not announced at all;
     *  - inside a transaction `committed` is false; after the outermost COMMIT every such statement is announced again
     *    with `committed === true` (and `affectedRows === null`), so caches invalidated early cannot be re-filled with
     *    pre-commit data, and re-warming can wait for the data to really exist.
     *
     * @param callable(string,bool,?int):void $listener
     */
    public function onWrite(callable $listener): void
    {
        $this->writeListeners[] = $listener;
    }

    private function notifyWrite(string $sql, bool $committed, ?int $affected): void
    {
        foreach ($this->writeListeners as $listener) {
            $listener($sql, $committed, $affected);
        }
    }

    private function dispatchWrites(): void
    {
        while ($this->queuedWrites !== []) {
            [$sql, $affected] = array_shift($this->queuedWrites);
            if ($affected === 0 && preg_match('/^\s*(INSERT|UPDATE|DELETE|REPLACE)\b/i', $sql)) {
                continue; // nothing changed, nothing to invalidate
            }
            if ($this->transactions > 0) {
                $this->pendingWrites[] = $sql;
                $this->notifyWrite($sql, false, $affected);
            } else {
                $this->notifyWrite($sql, true, $affected);
            }
        }
    }

    private static function isWrite(string $sql): bool
    {
        $keyword = strtoupper(strtok(ltrim($sql), " \t\n\r(") ?: '');
        return !in_array($keyword, ['SELECT', 'WITH', 'PRAGMA', 'SAVEPOINT', 'RELEASE', 'ROLLBACK', 'BEGIN', 'COMMIT', 'SET', 'SHOW', 'EXPLAIN', 'DESCRIBE', 'USE'], true)
            || ($keyword === 'WITH' && preg_match('/\b(INSERT|UPDATE|DELETE)\b/i', $sql) === 1);
    }

    public function listen(callable $listener): void
    {
        $this->listeners[] = $listener;
    }

    /**
     * @template T
     * @param \Closure(self):T $callback
     * @return T
     */
    public function transaction(\Closure $callback): mixed
    {
        $this->beginTransaction();
        try {
            $result = $callback($this);
        } catch (\Throwable $e) {
            $this->rollBack();
            throw $e;
        }
        $this->commit();
        return $result;
    }

    public function beginTransaction(): void
    {
        if ($this->transactions === 0) {
            $this->pdo()->beginTransaction();
        } else {
            $this->pdo()->exec('SAVEPOINT naluz_' . $this->transactions);
        }
        $this->transactions++;
    }

    public function commit(): void
    {
        $this->transactions--;
        if ($this->transactions === 0) {
            $this->pdo()->commit();
            $pending = array_unique($this->pendingWrites);
            $this->pendingWrites = [];
            foreach ($pending as $sql) {
                $this->notifyWrite($sql, true, null);
            }
        } else {
            $this->pdo()->exec('RELEASE SAVEPOINT naluz_' . $this->transactions);
        }
    }

    public function rollBack(): void
    {
        $this->transactions--;
        if ($this->transactions === 0) {
            $this->pdo()->rollBack();
            $this->pendingWrites = [];
        } else {
            $this->pdo()->exec('ROLLBACK TO SAVEPOINT naluz_' . $this->transactions);
        }
    }

    public function transactionLevel(): int
    {
        return $this->transactions;
    }
}
