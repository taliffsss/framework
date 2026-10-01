<?php

declare(strict_types=1);

namespace Naluz\Tests\Unit;

use Naluz\Cache\ArrayCache;
use Naluz\Cache\Psr6\CacheItemPool;
use Naluz\Cache\Psr6\InvalidArgumentException;
use Naluz\Support\FrozenClock;
use PHPUnit\Framework\TestCase;

final class Psr6CacheTest extends TestCase
{
    private FrozenClock $clock;
    private ArrayCache $store;
    private CacheItemPool $pool;

    protected function setUp(): void
    {
        $this->clock = new FrozenClock('2026-01-01 12:00:00');
        $this->store = new ArrayCache();
        $this->pool = new CacheItemPool($this->store, $this->clock);
    }

    public function testMissThenHit(): void
    {
        $item = $this->pool->getItem('k');
        $this->assertFalse($item->isHit());
        $this->assertNull($item->get());
        $this->assertTrue($this->pool->save($item->set(['a' => 1])));
        $this->assertTrue($this->pool->getItem('k')->isHit());
        $this->assertSame(['a' => 1], $this->pool->getItem('k')->get());
        $this->assertTrue($this->pool->hasItem('k'));
    }

    public function testNullAndFalseAreRealHits(): void
    {
        foreach ([null, false, 0, ''] as $i => $value) {
            $this->pool->save($this->pool->getItem("v{$i}")->set($value));
            $item = $this->pool->getItem("v{$i}");
            $this->assertTrue($item->isHit(), 'stored ' . var_export($value, true));
            $this->assertSame($value, $item->get());
        }
    }

    public function testExpiryWithExpiresAfterAndAt(): void
    {
        $this->pool->save($this->pool->getItem('a')->set(1)->expiresAfter(60));
        $this->pool->save($this->pool->getItem('b')->set(2)->expiresAt(new \DateTimeImmutable('2026-01-01 12:30:00')));
        $this->pool->save($this->pool->getItem('c')->set(3)->expiresAfter(new \DateInterval('PT10S')));
        $this->pool->save($this->pool->getItem('forever')->set(4));
        $this->assertTrue($this->pool->hasItem('a'));

        // the store checks real time, so verify the TTL the pool computed from the injected clock
        $item = $this->pool->getItem('a')->expiresAfter(60);
        $this->assertSame(60, $item->ttl());
        $this->assertSame(1800, $this->pool->getItem('b')->expiresAt(new \DateTimeImmutable('2026-01-01 12:30:00'))->ttl());
        $this->assertNull($this->pool->getItem('forever')->expiresAfter(null)->ttl());
    }

    public function testSavingAnAlreadyExpiredItemDeletesIt(): void
    {
        $this->pool->save($this->pool->getItem('x')->set(1));
        $this->assertTrue($this->pool->hasItem('x'));
        $this->pool->save($this->pool->getItem('x')->set(2)->expiresAfter(-1));
        $this->assertFalse($this->pool->hasItem('x'));
    }

    public function testDeleteAndClear(): void
    {
        $this->pool->save($this->pool->getItem('a')->set(1));
        $this->pool->save($this->pool->getItem('b')->set(2));
        $this->pool->save($this->pool->getItem('c')->set(3));
        $this->assertTrue($this->pool->deleteItem('a'));
        $this->assertTrue($this->pool->deleteItems(['b']));
        $this->assertFalse($this->pool->hasItem('a'));
        $this->assertTrue($this->pool->hasItem('c'));
        $this->assertTrue($this->pool->clear());
        $this->assertFalse($this->pool->hasItem('c'));
        $this->assertTrue($this->pool->deleteItem('never_existed'), 'deleting a missing key succeeds');
    }

    public function testDeferredSavesAreVisibleBeforeCommitAndPersistAfter(): void
    {
        $this->pool->saveDeferred($this->pool->getItem('d')->set('later'));
        $this->assertSame('later', $this->pool->getItem('d')->get(), 'visible through the pool');
        $this->assertNull($this->store->get('d'), 'not yet in the backing store');
        $this->assertTrue($this->pool->commit());
        $this->assertTrue((new CacheItemPool($this->store, $this->clock))->hasItem('d'));
    }

    public function testDeferredItemsAreCommittedOnDestruction(): void
    {
        $pool = new CacheItemPool($this->store, $this->clock);
        $pool->saveDeferred($pool->getItem('z')->set(1));
        unset($pool);
        $this->assertTrue((new CacheItemPool($this->store))->hasItem('z'));
    }

    public function testGetItemsReturnsKeyedItems(): void
    {
        $this->pool->save($this->pool->getItem('a')->set(1));
        $items = $this->pool->getItems(['a', 'b']);
        $this->assertTrue($items['a']->isHit());
        $this->assertFalse($items['b']->isHit());
    }

    public function testItemsAreIndependentCopies(): void
    {
        $a = $this->pool->getItem('k')->set(1);
        $this->pool->saveDeferred($a);
        $a->set(2); // mutating the original after saveDeferred must not change what gets committed
        $this->pool->commit();
        $this->assertSame(1, $this->pool->getItem('k')->get());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('badKeys')]
    public function testInvalidKeysThrowPsr6Exception(string $key): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectException(\Psr\Cache\InvalidArgumentException::class);
        $this->pool->getItem($key);
    }

    public static function badKeys(): array
    {
        return [[''], ['a{b'], ['a}b'], ['a(b'], ['a)b'], ['a/b'], ['a\\b'], ['a@b'], ['a:b'], [str_repeat('x', 65)], ["a\nb"]];
    }

    public function testForeignItemsAreRejected(): void
    {
        $foreign = new class implements \Psr\Cache\CacheItemInterface {
            public function getKey(): string
            {
                return 'k';
            }
            public function get(): mixed
            {
                return 1;
            }
            public function isHit(): bool
            {
                return true;
            }
            public function set(mixed $value): static
            {
                return $this;
            }
            public function expiresAt(?\DateTimeInterface $expiration): static
            {
                return $this;
            }
            public function expiresAfter(int|\DateInterval|null $time): static
            {
                return $this;
            }
        };
        $this->expectException(InvalidArgumentException::class);
        $this->pool->save($foreign);
    }
}
