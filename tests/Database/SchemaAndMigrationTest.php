<?php

declare(strict_types=1);

namespace Naluz\Tests\Database;

use Naluz\Database\Migrations\Migrator;
use Naluz\Tests\TestCase;

final class SchemaAndMigrationTest extends TestCase
{
    protected function migrate(): bool
    {
        return false;
    }

    public function testRunStatusRollback(): void
    {
        $migrator = new Migrator($this->db(), dirname(__DIR__, 2) . '/database/migrations');
        $total = count(glob(dirname(__DIR__, 2) . '/database/migrations/*.php'));
        $this->assertSame(array_fill(0, $total, false), array_column($migrator->status(), 'ran'));

        $ran = $migrator->run();
        $this->assertCount($total, $ran);
        $schema = $this->db()->schema();
        $this->assertTrue($schema->hasTable('users'));
        $this->assertTrue($schema->hasTable('posts'));
        $this->assertSame([], $migrator->run(), 'idempotent');
        $this->assertSame(array_fill(0, $total, true), array_column($migrator->status(), 'ran'));

        $rolled = $migrator->rollback();
        $this->assertSame(array_reverse($ran), $rolled, 'rolls back in reverse order');
        $this->assertFalse($schema->hasTable('users'));
        $this->assertFalse($schema->hasTable('posts'));
    }

    public function testSchemaFeatures(): void
    {
        $s = $this->db()->schema();
        $s->create('things', function ($t) {
            $t->id();
            $t->string('slug', 50)->unique();
            $t->decimal('price', 8, 2)->default(0);
            $t->boolean('active')->default(true);
            $t->json('meta')->nullable();
            $t->string('label')->default("it's");
            $t->index(['active', 'price']);
            $t->timestamps();
        });
        $this->db()->table('things')->insert(['slug' => 'a']);
        $row = $this->db()->table('things')->first();
        $this->assertSame("it's", $row['label'], 'default values are quoted safely');
        $this->assertSame(1, $row['active']);

        $s->table('things', fn ($t) => $t->string('extra')->nullable());
        $this->db()->table('things')->update(['extra' => 'x']);
        $this->assertSame('x', $this->db()->table('things')->value('extra'));

        $this->expectException(\Naluz\Database\QueryException::class);
        $this->db()->table('things')->insert(['slug' => 'a']); // unique index enforced
    }

    public function testInvalidIdentifiersAreRejectedInDdl(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->db()->schema()->create('x; DROP TABLE users', fn ($t) => $t->id());
    }
}
