<?php

declare(strict_types=1);

namespace Naluz\Tests\NoSql;

use Naluz\NoSql\DocumentCollection;
use Naluz\NoSql\DuplicateKeyException;
use Naluz\NoSql\FileStore;

final class FileCollectionTest extends CollectionContract
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/naluz-nosql-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->dir);
    }

    protected function collection(): DocumentCollection
    {
        return (new FileStore($this->dir))->collection('things');
    }

    public function testDataPersistsAcrossInstances(): void
    {
        $a = (new FileStore($this->dir))->collection('users');
        $a->createIndex(['email' => 1], ['unique' => true]);
        $id = $a->insertOne(['email' => 'a@x.io', 'n' => 1.0, 'big' => 9007199254740991]);

        $b = (new FileStore($this->dir))->collection('users');   // a "different request"
        $doc = $b->findOne(['_id' => $id]);
        $this->assertSame('a@x.io', $doc['email']);
        $this->assertSame(1.0, $doc['n'], 'floats stay floats');
        $this->assertSame(9007199254740991, $doc['big']);
        $this->expectException(DuplicateKeyException::class);
        $b->insertOne(['email' => 'a@x.io']);   // the unique index persisted too
    }

    public function testConcurrentWritersNeverLoseUpdates(): void
    {
        $c = $this->collection();
        $c->insertOne(['_id' => 'counter', 'n' => 0]);
        $worker = <<<'PHP'
            require $argv[1] . '/vendor/autoload.php';
            $c = (new Naluz\NoSql\FileStore($argv[2]))->collection('things');
            for ($i = 0; $i < 25; $i++) { $c->updateOne(['_id' => 'counter'], ['$inc' => ['n' => 1]]); }
        PHP;
        $procs = [];
        for ($i = 0; $i < 4; $i++) {
            $procs[] = proc_open([PHP_BINARY, '-r', $worker, dirname(__DIR__, 2), $this->dir], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
        }
        foreach ($procs as $p) {
            proc_close($p);
        }
        $this->assertSame(100, $c->findOne(['_id' => 'counter'])['n'], '4 processes x 25 increments, none lost');
    }

    public function testNoTemporaryFilesAreLeftBehind(): void
    {
        $c = $this->collection();
        $c->insertOne(['a' => 1]);
        $c->updateMany([], ['$set' => ['b' => 2]]);
        $this->assertSame([], glob($this->dir . '/*.tmp') ?: []);
    }

    public function testReadsDoNotRewriteTheFile(): void
    {
        $c = $this->collection();
        $c->insertOne(['a' => 1]);
        $file = $this->dir . '/things.json';
        $mtime = filemtime($file);
        clearstatcache();
        sleep(1);
        $c->find([]);
        $c->count();
        clearstatcache();
        $this->assertSame($mtime, filemtime($file));
    }

    public function testCorruptFilesAreNeverOverwritten(): void
    {
        $c = $this->collection();
        $c->insertOne(['a' => 1]);
        file_put_contents($this->dir . '/things.json', '{"docs": truncated');
        foreach ([fn () => $c->find([]), fn () => $c->insertOne(['b' => 2])] as $op) {
            try {
                $op();
                $this->fail('a corrupt file was silently accepted');
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('corrupt', $e->getMessage());
            }
        }
        $this->assertSame('{"docs": truncated', file_get_contents($this->dir . '/things.json'), 'left untouched for a human to inspect');
    }

    public function testCollectionNamesCannotEscapeTheDirectory(): void
    {
        $store = new FileStore($this->dir);
        foreach (['../evil', '..', 'a/b', 'a\\b', '', '.hidden', "a\0b", str_repeat('x', 65), 'x..y/../z'] as $bad) {
            try {
                $store->collection($bad);
                $this->fail("accepted collection name [{$bad}]");
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertFileDoesNotExist(dirname($this->dir) . '/evil.json');
    }

    public function testStoreListsAndDropsCollections(): void
    {
        $store = new FileStore($this->dir);
        $store->collection('b')->insertOne(['x' => 1]);
        $store->collection('a')->insertOne(['x' => 1]);
        $this->assertSame(['a', 'b'], $store->collections());
        $store->dropCollection('a');
        $this->assertSame(['b'], $store->collections());
    }

    public function testFilesAreNotWorldReadable(): void
    {
        $this->collection()->insertOne(['secret' => 1]);
        $this->assertSame('0660', substr(sprintf('%o', fileperms($this->dir . '/things.json')), -4));
    }
}
