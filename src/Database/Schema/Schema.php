<?php

declare(strict_types=1);

namespace Naluz\Database\Schema;

use Naluz\Database\Connection;

/** DDL builder for MySQL, PostgreSQL and SQLite. */
final class Schema
{
    public function __construct(private readonly Connection $db)
    {
    }

    public function create(string $table, \Closure $callback): void
    {
        $blueprint = new Blueprint($table);
        $callback($blueprint);
        $g = $this->db->grammar();

        $lines = array_map(fn (ColumnDefinition $c) => $this->columnSql($c), $blueprint->columns);
        foreach ($blueprint->columns as $c) {
            if ($c->foreign) {
                $lines[] = sprintf(
                    'FOREIGN KEY (%s) REFERENCES %s (%s) ON DELETE %s',
                    $g->wrap($c->name),
                    $g->wrap($c->foreign['table']),
                    $g->wrap($c->foreign['column']),
                    $this->action($c->foreign['onDelete'])
                );
            }
        }
        $this->db->statement(sprintf(
            'CREATE TABLE %s (%s)%s',
            $g->wrap($table),
            implode(', ', $lines),
            $this->db->driver() === 'mysql' ? ' DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci' : ''
        ));
        $this->indexes($blueprint);
    }

    public function table(string $table, \Closure $callback): void
    {
        $blueprint = new Blueprint($table);
        $callback($blueprint);
        $g = $this->db->grammar();
        foreach ($blueprint->columns as $c) {
            $this->db->statement('ALTER TABLE ' . $g->wrap($table) . ' ADD COLUMN ' . $this->columnSql($c));
        }
        foreach ($blueprint->dropColumns as $col) {
            $this->db->statement('ALTER TABLE ' . $g->wrap($table) . ' DROP COLUMN ' . $g->wrap($col));
        }
        $this->indexes($blueprint);
    }

    public function drop(string $table): void
    {
        $this->db->statement('DROP TABLE ' . $this->db->grammar()->wrap($table));
    }

    public function dropIfExists(string $table): void
    {
        $this->db->statement('DROP TABLE IF EXISTS ' . $this->db->grammar()->wrap($table));
    }

    public function hasTable(string $table): bool
    {
        return match ($this->db->driver()) {
            'sqlite' => $this->db->selectOne("SELECT name FROM sqlite_master WHERE type = 'table' AND name = ?", [$table]) !== null,
            'pgsql' => $this->db->selectOne('SELECT 1 FROM information_schema.tables WHERE table_schema = current_schema() AND table_name = ?', [$table]) !== null,
            default => $this->db->selectOne('SELECT 1 FROM information_schema.tables WHERE table_schema = database() AND table_name = ?', [$table]) !== null,
        };
    }

    private function indexes(Blueprint $b): void
    {
        $g = $this->db->grammar();
        $all = $b->indexes;
        foreach ($b->columns as $c) {
            if ($c->unique) {
                $all[] = ['columns' => [$c->name], 'unique' => true, 'name' => null];
            } elseif ($c->index || $c->foreign) {
                $all[] = ['columns' => [$c->name], 'unique' => false, 'name' => null];
            }
        }
        foreach ($all as $idx) {
            $name = $idx['name'] ?? $b->table . '_' . implode('_', $idx['columns']) . ($idx['unique'] ? '_unique' : '_index');
            $this->db->statement(sprintf(
                'CREATE %sINDEX %s ON %s (%s)',
                $idx['unique'] ? 'UNIQUE ' : '',
                $g->quote($name),
                $g->wrap($b->table),
                $g->columnize($idx['columns'])
            ));
        }
    }

    private function action(string $action): string
    {
        $action = strtoupper($action);
        if (!in_array($action, ['CASCADE', 'RESTRICT', 'SET NULL', 'NO ACTION'], true)) {
            throw new \InvalidArgumentException("Illegal foreign key action [{$action}].");
        }
        return $action;
    }

    private function columnSql(ColumnDefinition $c): string
    {
        $driver = $this->db->driver();
        $name = $this->db->grammar()->wrap($c->name);

        if ($c->type === 'id') {
            return $name . ' ' . match ($driver) {
                'sqlite' => 'INTEGER PRIMARY KEY AUTOINCREMENT',
                'pgsql' => 'BIGSERIAL PRIMARY KEY',
                default => 'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY',
            };
        }

        $type = match ($c->type) {
            'string' => 'VARCHAR(' . (int) $c->params['length'] . ')',
            'text' => 'TEXT',
            'integer' => 'INTEGER',
            'bigInteger' => $driver === 'sqlite' ? 'INTEGER' : 'BIGINT',
            'boolean' => $driver === 'mysql' ? 'TINYINT(1)' : ($driver === 'sqlite' ? 'INTEGER' : 'BOOLEAN'),
            'decimal' => sprintf('DECIMAL(%d, %d)', $c->params['precision'], $c->params['scale']),
            'float' => $driver === 'pgsql' ? 'DOUBLE PRECISION' : ($driver === 'sqlite' ? 'REAL' : 'DOUBLE'),
            'json' => $driver === 'pgsql' ? 'JSONB' : ($driver === 'sqlite' ? 'TEXT' : 'JSON'),
            'date' => 'DATE',
            'timestamp' => $driver === 'pgsql' ? 'TIMESTAMP(0) WITHOUT TIME ZONE' : ($driver === 'mysql' ? 'TIMESTAMP NULL' : 'DATETIME'),
            default => throw new \InvalidArgumentException("Unknown column type [{$c->type}]."),
        };
        if ($c->unsigned && $driver === 'mysql' && in_array($c->type, ['integer', 'bigInteger'], true)) {
            $type .= ' UNSIGNED';
        }

        $sql = $name . ' ' . $type . ($c->nullable ? ' NULL' : ' NOT NULL');
        if ($c->hasDefault) {
            $sql .= ' DEFAULT ' . match (true) {
                $c->default === null => 'NULL',
                is_bool($c->default) => $c->default ? '1' : '0',
                is_int($c->default), is_float($c->default) => (string) $c->default,
                default => $this->db->pdo()->quote((string) $c->default),
            };
        } elseif ($c->useCurrent) {
            $sql .= ' DEFAULT CURRENT_TIMESTAMP';
        }
        return $sql;
    }
}
