<?php

declare(strict_types=1);

namespace Naluz\Tests\Unit;

use Naluz\Support\Arr;
use Naluz\Support\Collection;
use Naluz\Support\Env;
use Naluz\Support\Str;
use PHPUnit\Framework\TestCase;

final class SupportTest extends TestCase
{
    public function testArrDotNotation(): void
    {
        $a = ['a' => ['b' => ['c' => 1]]];
        $this->assertSame(1, Arr::get($a, 'a.b.c'));
        $this->assertSame('x', Arr::get($a, 'a.z', 'x'));
        $this->assertTrue(Arr::has($a, 'a.b'));
        Arr::set($a, 'a.b.d', 2);
        $this->assertSame(2, $a['a']['b']['d']);
        Arr::forget($a, 'a.b.c');
        $this->assertFalse(Arr::has($a, 'a.b.c'));
        $this->assertSame(['a' => 1], Arr::only(['a' => 1, 'b' => 2], ['a']));
    }

    public function testStr(): void
    {
        $this->assertSame('user_profile', Str::snake('UserProfile'));
        $this->assertSame('UserProfile', Str::studly('user_profile'));
        $this->assertSame('userProfile', Str::camel('user_profile'));
        $this->assertSame('posts', Str::plural('post'));
        $this->assertSame('categories', Str::plural('category'));
        $this->assertSame('boxes', Str::plural('box'));
        $this->assertSame('people', Str::plural('person'));
        $this->assertSame('post', Str::singular('posts'));
        $this->assertSame('category', Str::singular('categories'));
        $this->assertSame('hello-world', Str::slug('Hello, World!'));
        $this->assertSame(32, strlen(Str::random(32)));
    }

    public function testCollection(): void
    {
        $c = new Collection([['id' => 1, 'g' => 'a', 'n' => 3], ['id' => 2, 'g' => 'b', 'n' => 1], ['id' => 3, 'g' => 'a', 'n' => 2]]);
        $this->assertSame([1, 2, 3], $c->pluck('id')->all());
        $this->assertSame([1 => 3, 2 => 1, 3 => 2], $c->pluck('n', 'id')->all());
        $this->assertSame(6, $c->sum('n'));
        $this->assertSame([2, 3, 1], $c->sortBy('n')->pluck('id')->values()->all());
        $this->assertCount(2, $c->groupBy('g')['a']);
        $this->assertSame(2, $c->keyBy('id')[2]['id']);
        $this->assertSame(1, $c->first()['id']);
        $this->assertSame('[1,2]', json_encode(new Collection([1, 2])));
        $this->assertTrue((new Collection())->isEmpty());
    }

    public function testEnvParser(): void
    {
        $parsed = Env::parse("# comment\nA=1\nB=\"two words\"\nC='x # y'\nexport D=val # trailing\nE=\n");
        $this->assertSame(['A' => '1', 'B' => 'two words', 'C' => 'x # y', 'D' => 'val', 'E' => ''], $parsed);
        Env::set('BOOL_T', 'true');
        $this->assertTrue(Env::get('BOOL_T'));
        $this->assertSame('dflt', Env::get('NOPE_NOT_SET', 'dflt'));
        Env::flush();
    }
}
