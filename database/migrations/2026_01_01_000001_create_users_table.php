<?php

declare(strict_types=1);

use Naluz\Database\Migrations\Migration;
use Naluz\Database\Schema\Schema;

return new class extends Migration {
    public function up(Schema $schema): void
    {
        $schema->create('users', function ($t) {
            $t->id();
            $t->string('name');
            $t->string('email')->unique();
            $t->string('password');
            $t->timestamps();
        });
    }

    public function down(Schema $schema): void
    {
        $schema->dropIfExists('users');
    }
};
