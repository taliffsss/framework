<?php

declare(strict_types=1);

namespace Naluz\Database;

use Naluz\Database\Query\Builder;

/** Builds and caches named connections from `config/database.php`. */
final class DatabaseManager
{
    /** @var array<string,Connection> */
    private array $connections = [];

    /** @param array{default:string,connections:array<string,array<string,mixed>>} $config */
    public function __construct(private readonly array $config)
    {
    }

    public function connection(?string $name = null): Connection
    {
        $name ??= $this->config['default'];
        return $this->connections[$name] ??= $this->make($name);
    }

    public function extend(string $name, Connection $connection): void
    {
        $this->connections[$name] = $connection;
    }

    public function table(string $table, ?string $as = null): Builder
    {
        return $this->connection()->table($table, $as);
    }

    private function make(string $name): Connection
    {
        $config = $this->config['connections'][$name]
            ?? throw new \InvalidArgumentException("Database connection [{$name}] is not configured.");

        $driver = $config['driver'] ?? throw new \InvalidArgumentException("Connection [{$name}] has no driver.");
        $options = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,   // real server-side prepares: no client-side string interpolation
            \PDO::ATTR_STRINGIFY_FETCHES => false,
        ];

        $factory = match ($driver) {
            'sqlite' => function () use ($config, $options): \PDO {
                $pdo = new \PDO('sqlite:' . ($config['database'] ?? ':memory:'), null, null, $options);
                $pdo->exec('PRAGMA foreign_keys = ON');
                return $pdo;
            },
            'mysql' => fn () => new \PDO(
                sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $config['host'] ?? '127.0.0.1', $config['port'] ?? 3306, $config['database'] ?? '', $config['charset'] ?? 'utf8mb4'),
                $config['username'] ?? null,
                $config['password'] ?? null,
                $options + [\PDO::MYSQL_ATTR_INIT_COMMAND => "SET sql_mode='STRICT_ALL_TABLES,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO'"]
            ),
            'pgsql' => fn () => new \PDO(
                sprintf('pgsql:host=%s;port=%s;dbname=%s', $config['host'] ?? '127.0.0.1', $config['port'] ?? 5432, $config['database'] ?? ''),
                $config['username'] ?? null,
                $config['password'] ?? null,
                $options
            ),
            default => throw new \InvalidArgumentException("Unsupported database driver [{$driver}]."),
        };

        return new Connection($factory, $driver, $name);
    }
}
