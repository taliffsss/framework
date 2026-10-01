<?php

declare(strict_types=1);

namespace Naluz\Tests\NoSql;

use Naluz\NoSql\DocumentCollection;
use Naluz\NoSql\MemoryStore;

final class MemoryCollectionTest extends CollectionContract
{
    protected function collection(): DocumentCollection
    {
        return (new MemoryStore())->collection('things');
    }

    public function testStoreManagesCollections(): void
    {
        $s = new MemoryStore();
        $s->collection('a')->insertOne(['x' => 1]);
        $s->collection('b');
        $this->assertSame(['a', 'b'], $s->collections());
        $this->assertSame(1, $s->collection('a')->count(), 'the same collection object state is shared');
        $s->dropCollection('a');
        $this->assertSame(['b'], $s->collections());
        $this->expectException(\InvalidArgumentException::class);
        $s->collection('../etc/passwd');
    }
}
