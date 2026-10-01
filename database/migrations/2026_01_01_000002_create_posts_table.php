<?php

declare(strict_types=1);

use Naluz\Database\Migrations\Migration;
use Naluz\Database\Schema\Schema;

return new class extends Migration {
    public function up(Schema $schema): void
    {
        $schema->create('posts', function ($t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->string('title', 200);
            $t->text('body');
            $t->boolean('published')->default(false);
            $t->timestamps();
            $t->softDeletes();
        });
    }

    public function down(Schema $schema): void
    {
        $schema->dropIfExists('posts');
    }
};
