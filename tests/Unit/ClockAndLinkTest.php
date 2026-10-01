<?php

declare(strict_types=1);

namespace Naluz\Tests\Unit;

use Naluz\Database\Paginator;
use Naluz\Http\Link;
use Naluz\Http\LinkProvider;
use Naluz\Http\Response;
use Naluz\Support\Collection;
use Naluz\Support\FrozenClock;
use Naluz\Support\SystemClock;
use PHPUnit\Framework\TestCase;

final class ClockAndLinkTest extends TestCase
{
    public function testSystemClockUsesTheGivenTimezone(): void
    {
        $now = (new SystemClock(new \DateTimeZone('Asia/Manila')))->now();
        $this->assertSame('Asia/Manila', $now->getTimezone()->getName());
        $this->assertEqualsWithDelta(time(), $now->getTimestamp(), 2);
    }

    public function testFrozenClock(): void
    {
        $c = new FrozenClock('2026-05-05 10:00:00');
        $this->assertSame('2026-05-05 10:00:00', $c->now()->format('Y-m-d H:i:s'));
        $this->assertSame($c->now(), $c->now(), 'does not tick');
        $c->advance('+90 minutes');
        $this->assertSame('2026-05-05 11:30:00', $c->now()->format('Y-m-d H:i:s'));
    }

    public function testLinkIsImmutableAndTemplateAware(): void
    {
        $a = new Link('https://x.test/items{?page}', ['next']);
        $this->assertTrue($a->isTemplated());
        $b = $a->withRel('collection')->withAttribute('title', 'Items')->withHref('/plain');
        $this->assertSame(['next'], $a->getRels(), 'original untouched');
        $this->assertSame(['next', 'collection'], $b->getRels());
        $this->assertSame(['title' => 'Items'], $b->getAttributes());
        $this->assertFalse($b->isTemplated());
        $this->assertSame(['collection'], $b->withoutRel('next')->getRels());
        $this->assertSame([], $b->withoutAttribute('title')->getAttributes());
    }

    public function testProviderFiltersByRel(): void
    {
        $p = (new LinkProvider())->withLink(new Link('/a', ['next']))->withLink(new Link('/b', ['prev']));
        $this->assertCount(1, $p->getLinksByRel('next'));
        $this->assertCount(2, $p->getLinks());
        $first = $p->getLinks()[0];
        $this->assertCount(1, $p->withoutLink($first)->getLinks());
    }

    public function testLinkHeaderSerialisation(): void
    {
        $h = LinkProvider::header([
            new Link('https://x.test/p2', ['next'], ['title' => 'Page "2"']),
            new Link('/a', ['alternate', 'author'], ['hreflang' => ['en', 'fr'], 'x' => true]),
        ]);
        $this->assertSame('<https://x.test/p2>; rel="next"; title="Page \"2\"", </a>; rel="alternate author"; hreflang="en"; hreflang="fr"; x', $h);
        $response = (new LinkProvider([new Link('/n', ['next'])]))->applyTo(new Response());
        $this->assertSame('</n>; rel="next"', $response->getHeaderLine('Link'));
    }

    public function testLinkHeaderRejectsInjection(): void
    {
        foreach ([new Link("/x\r\nSet-Cookie: a=b", ['next']), new Link('/x', ["next\r\nX: y"]), new Link('/x>; rel="evil"', ['next'])] as $bad) {
            try {
                LinkProvider::header([$bad]);
                $this->fail('accepted malicious link');
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->expectException(\InvalidArgumentException::class);
        LinkProvider::header([new Link('/x', ['next'], ['bad name' => 'v'])]);
    }

    public function testPaginatorBuildsNavigationLinks(): void
    {
        $page = new Paginator(new Collection([1, 2]), 95, 10, 3);
        $header = LinkProvider::header($page->links('https://api.test/posts', ['q' => 'x'])->getLinks());
        $this->assertStringContainsString('page=1', $header);
        $this->assertStringContainsString('rel="prev"', $header);
        $this->assertStringContainsString('page=4&per_page=10>; rel="next"', $header);
        $this->assertStringContainsString('page=10', $header);

        $only = LinkProvider::header((new Paginator(new Collection(), 3, 10, 1))->links('/p')->getLinks());
        $this->assertStringNotContainsString('rel="next"', $only);
        $this->assertStringNotContainsString('rel="prev"', $only);
    }
}
