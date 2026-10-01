<?php

declare(strict_types=1);

use Naluz\Database\Migrations\Migration;
use Naluz\Database\Schema\Schema;

return new class extends Migration {
    public function up(Schema $schema): void
    {
        $schema->create('jobs', function ($t) {
            $t->id();
            $t->string('queue');
            $t->text('payload');
            $t->integer('attempts')->default(0);
            $t->integer('reserved_at')->nullable();
            $t->integer('available_at');
            $t->integer('created_at');
            $t->index(['queue', 'available_at']);
        });
        $schema->create('failed_jobs', function ($t) {
            $t->id();
            $t->string('queue');
            $t->text('payload');
            $t->text('exception');
            $t->timestamp('failed_at')->nullable();
        });
    }

    public function down(Schema $schema): void
    {
        $schema->dropIfExists('failed_jobs');
        $schema->dropIfExists('jobs');
    }
};
