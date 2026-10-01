<?php

declare(strict_types=1);

namespace Naluz\Database;

use Naluz\Security\DecryptException;
use Naluz\Security\Encrypter;
use Psr\SimpleCache\CacheInterface;

/**
 * Transparent query-result cache for the ORM (enabled with MODEL_CACHING=true).
 *
 * How correctness is kept:
 *  - Entries are keyed by connection + SQL + bindings + a random *version token per table involved* (and a global token).
 *  - Any write to a table replaces its token, so every cached query that touched it becomes unreachable at once.
 *    Writes are detected at the connection, so Model::save(), query-builder updates, pivot attach/detach, raw statements
 *    and migrations all invalidate — not just model methods.
 *  - DELETE / TRUNCATE / DDL flush the whole model cache by default, because foreign-key cascades modify tables the
 *    statement never names (`flush_on_delete => 'table'` opts out).
 *  - Reads inside a transaction bypass the cache; writes in a transaction invalidate again after COMMIT.
 *  - Queries with raw SQL are never cached (their table dependencies are unknown).
 *  - The token is read BEFORE the query runs, so a concurrent write can never be masked by a stale store.
 *  - TTL bounds staleness from changes this application cannot see (other apps, DB triggers, manual SQL).
 */
final class ModelCache
{
    public int $hits = 0;
    public int $misses = 0;

    /** @var list<string> */
    private array $exclude;

    /**
     * @param list<string> $excludeTables tables that are never cached
     * @param 'all'|'table' $flushOnDelete
     */
    public function __construct(
        private readonly CacheInterface $store,
        private readonly int $ttl = 3600,
        private readonly string $prefix = 'naluz_mc_',
        array $excludeTables = [],
        private readonly string $flushOnDelete = 'all',
        private readonly ?Encrypter $encrypter = null,
        private readonly int $versionTtl = 2_592_000,
    ) {
        $this->exclude = array_map('strtolower', $excludeTables);
    }

    public function defaultTtl(): int
    {
        return $this->ttl;
    }

    /** @param list<string> $tables */
    public function canCache(array $tables): bool
    {
        return $tables !== [] && array_intersect($tables, $this->exclude) === [];
    }

    /**
     * @template T
     * @param list<string> $tables every table the query reads
     * @param \Closure():T $load runs the real query
     * @return T
     */
    public function remember(string $connection, array $tables, string $fingerprint, ?int $ttl, \Closure $load): mixed
    {
        // 1. versions first (see class docs), 2. lookup, 3. load + store under the *earlier* versions
        $versions = $this->versions($connection, $tables);
        $key = $this->prefix . 'q_' . hash('sha256', $connection . '|' . $versions . '|' . $fingerprint);

        $stored = $this->store->get($key);
        if ($stored !== null) {
            $value = $this->decode($stored);
            if ($value !== null) {
                $this->hits++;
                return $value['v'];
            }
        }
        $this->misses++;
        $result = $load();
        $this->store->set($key, $this->encode(['v' => $result]), max(1, $ttl ?? $this->ttl));
        return $result;
    }

    /** React to a write statement: find the tables it touches and invalidate. */
    public function handleWrite(string $connection, string $sql): void
    {
        $sql = ltrim($sql);
        $keyword = strtoupper(strtok($sql, " \t\n\r(") ?: '');

        if (
            in_array($keyword, ['DELETE', 'TRUNCATE', 'DROP', 'ALTER', 'CREATE', 'RENAME'], true)
            && $this->flushOnDelete === 'all'
        ) {
            $this->flushAll();
            return;
        }
        $table = $this->tableOf($sql);
        if ($table === null) {
            $this->flushAll(); // unknown statement shape: the only safe answer is "everything"
            return;
        }
        $this->bump($connection, $table);
        if (in_array($keyword, ['DROP', 'ALTER', 'CREATE', 'RENAME', 'TRUNCATE'], true)) {
            $this->flushAll();
        }
    }

    public function flushTable(string $connection, string $table): void
    {
        $this->bump($connection, strtolower($table));
    }

    public function flushAll(): void
    {
        $this->store->set($this->prefix . 'ver_all', bin2hex(random_bytes(8)), $this->versionTtl);
    }

    /** Remove every entry (not just invalidate). Useful for the local driver to reclaim disk. */
    public function purge(): bool
    {
        return $this->store->clear();
    }

    // ------------------------------------------------------------------ internals

    /** @param list<string> $tables */
    private function versions(string $connection, array $tables): string
    {
        $keys = [$this->prefix . 'ver_all'];
        foreach ($tables as $t) {
            $keys[] = $this->prefix . 'ver_' . $connection . '_' . $t;
        }
        $found = (array) $this->store->getMultiple($keys);
        $out = [];
        foreach ($keys as $k) {
            $token = $found[$k] ?? null;
            if (!is_string($token) || $token === '') {
                $token = bin2hex(random_bytes(8));
                $this->store->set($k, $token, $this->versionTtl);
            }
            $out[] = $token;
        }
        return implode('.', $out);
    }

    private function bump(string $connection, string $table): void
    {
        $this->store->set($this->prefix . 'ver_' . $connection . '_' . $table, bin2hex(random_bytes(8)), $this->versionTtl);
    }

    private function tableOf(string $sql): ?string
    {
        $id = '[`"\[]?([A-Za-z_][A-Za-z0-9_$]*)[`"\]]?';
        foreach (
            [
            "/^INSERT\\s+(?:OR\\s+\\w+\\s+|IGNORE\\s+)?INTO\\s+{$id}/i",
            "/^REPLACE\\s+INTO\\s+{$id}/i",
            "/^UPDATE\\s+(?:OR\\s+\\w+\\s+)?{$id}/i",
            "/^DELETE\\s+FROM\\s+{$id}/i",
            "/^TRUNCATE\\s+(?:TABLE\\s+)?{$id}/i",
            "/^(?:DROP|ALTER)\\s+TABLE\\s+(?:IF\\s+EXISTS\\s+)?{$id}/i",
            "/^CREATE\\s+(?:UNIQUE\\s+)?INDEX\\s+.+?\\s+ON\\s+{$id}/i",
            "/^CREATE\\s+TABLE\\s+(?:IF\\s+NOT\\s+EXISTS\\s+)?{$id}/i",
            ] as $pattern
        ) {
            if (preg_match($pattern, $sql, $m)) {
                return strtolower($m[1]);
            }
        }
        return null;
    }

    private function encode(array $payload): mixed
    {
        return $this->encrypter === null ? $payload : $this->encrypter->encrypt($payload);
    }

    /** @return array{v:mixed}|null */
    private function decode(mixed $stored): ?array
    {
        if ($this->encrypter !== null) {
            try {
                $stored = is_string($stored) ? $this->encrypter->decrypt($stored) : null;
            } catch (DecryptException | \JsonException) {
                return null; // tampered or from an old key: treat as a miss
            }
        }
        return is_array($stored) && array_key_exists('v', $stored) ? $stored : null;
    }
}
