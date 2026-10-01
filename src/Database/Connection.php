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
        }
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
        } else {
            $this->pdo()->exec('RELEASE SAVEPOINT naluz_' . $this->transactions);
        }
    }

    public function rollBack(): void
    {
        $this->transactions--;
        if ($this->transactions === 0) {
            $this->pdo()->rollBack();
        } else {
            $this->pdo()->exec('ROLLBACK TO SAVEPOINT naluz_' . $this->transactions);
        }
    }

    public function transactionLevel(): int
    {
        return $this->transactions;
    }
}
