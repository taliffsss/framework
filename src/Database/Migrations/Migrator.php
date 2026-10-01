<?php

declare(strict_types=1);

namespace Naluz\Database\Migrations;

use Naluz\Database\Connection;

final class Migrator
{
    private const TABLE = 'migrations';

    public function __construct(private readonly Connection $db, private readonly string $path)
    {
    }

    /** @return list<string> names of migrations that ran */
    public function run(): array
    {
        $this->ensureTable();
        $ran = $this->ran();
        $batch = (int) $this->db->table(self::TABLE)->max('batch') + 1;
        $done = [];
        foreach ($this->files() as $name => $file) {
            if (in_array($name, $ran, true)) {
                continue;
            }
            $migration = $this->load($file);
            $up = fn () => $migration->up($this->db->schema());
            $this->db->transactionalDdl() ? $this->db->transaction($up) : $up();
            $this->db->table(self::TABLE)->insert(['migration' => $name, 'batch' => $batch]);
            $done[] = $name;
        }
        return $done;
    }

    /** Roll back the last batch(es). @return list<string> */
    public function rollback(int $steps = 1): array
    {
        $this->ensureTable();
        $files = $this->files();
        $rolled = [];
        for ($i = 0; $i < $steps; $i++) {
            $batch = (int) $this->db->table(self::TABLE)->max('batch');
            if ($batch === 0) {
                break;
            }
            $names = $this->db->table(self::TABLE)->where('batch', $batch)->orderBy('id', 'desc')->pluck('migration')->all();
            foreach ($names as $name) {
                if (!isset($files[$name])) {
                    throw new \RuntimeException("Migration file for [{$name}] is missing.");
                }
                $migration = $this->load($files[$name]);
                $down = fn () => $migration->down($this->db->schema());
                $this->db->transactionalDdl() ? $this->db->transaction($down) : $down();
                $this->db->table(self::TABLE)->where('migration', $name)->delete();
                $rolled[] = $name;
            }
        }
        return $rolled;
    }

    /** @return list<array{migration:string,ran:bool}> */
    public function status(): array
    {
        $this->ensureTable();
        $ran = $this->ran();
        return array_map(fn (string $n) => ['migration' => $n, 'ran' => in_array($n, $ran, true)], array_keys($this->files()));
    }

    /** @return list<string> */
    private function ran(): array
    {
        return $this->db->table(self::TABLE)->orderBy('id')->pluck('migration')->all();
    }

    /** @return array<string,string> name => path, sorted */
    private function files(): array
    {
        $files = [];
        foreach (glob(rtrim($this->path, '/\\') . '/*.php') ?: [] as $file) {
            $files[basename($file, '.php')] = $file;
        }
        ksort($files);
        return $files;
    }

    private function load(string $file): Migration
    {
        $migration = require $file;
        if (!$migration instanceof Migration) {
            throw new \RuntimeException("[{$file}] must return an instance of " . Migration::class . '.');
        }
        return $migration;
    }

    private function ensureTable(): void
    {
        $schema = $this->db->schema();
        if (!$schema->hasTable(self::TABLE)) {
            $schema->create(self::TABLE, function ($t) {
                $t->id();
                $t->string('migration');
                $t->integer('batch');
            });
        }
    }
}
