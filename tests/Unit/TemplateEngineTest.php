<?php

declare(strict_types=1);

namespace Naluz\Tests\Unit;

use Naluz\View\Compiler;
use Naluz\View\Factory;
use PHPUnit\Framework\TestCase;

final class TemplateEngineTest extends TestCase
{
    private string $dir;
    private Factory $views;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/naluz-tpl-' . bin2hex(random_bytes(4));
        mkdir($this->dir . '/views/layouts', 0775, true);
        $this->views = new Factory($this->dir . '/views', $this->dir . '/cache');
    }

    protected function tearDown(): void
    {
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) {
            $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
        }
        rmdir($this->dir);
    }

    private function tpl(string $name, string $source): void
    {
        $path = $this->dir . '/views/' . $name . '.naluz.php';
        @mkdir(dirname($path), 0775, true);
        file_put_contents($path, $source);
    }

    private function render(string $source, array $data = []): string
    {
        $this->tpl('t', $source);
        return $this->views->render('t', $data);
    }

    public function testEchoIsEscapedByDefaultAndRawIsExplicit(): void
    {
        $xss = '<script>alert("x")</script>';
        $this->assertSame('&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;', $this->render('{{ $v }}', ['v' => $xss]));
        $this->assertSame($xss, $this->render('{!! $v !!}', ['v' => $xss]));
        $this->assertSame('&#039;&gt;', $this->render('{{ $v }}', ['v' => "'>"]));
        $this->assertSame('x', $this->render('{{ $missing ?? "x" }}'));
        $this->assertSame('5', $this->render('{{ 2 + 3 }}'));
    }

    public function testCommentsAndEscapedBraces(): void
    {
        $this->assertSame('ab', $this->render('a{{-- hidden {{ $x }} --}}b'));
        $this->assertSame('{{ $v }} and 1', $this->render('@{{ $v }} and {{ $v }}', ['v' => 1]));
    }

    public function testConditionals(): void
    {
        $tpl = '@if($n > 5)big@elseif($n > 1)mid@else small @endif';
        $this->assertSame('big', $this->render($tpl, ['n' => 9]));
        $this->assertSame('mid', $this->render($tpl, ['n' => 3]));
        $this->assertSame(' small ', $this->render($tpl, ['n' => 0]));
        $this->assertSame('yes', $this->render('@unless($f)yes@endunless', ['f' => false]));
        $this->assertSame('set', $this->render('@isset($a)set@endisset', ['a' => 1]));
        $this->assertSame('', $this->render('@isset($a)set@endisset'));
        $this->assertSame('E', $this->render('@empty($a)E@endempty', ['a' => []]));
        $this->assertSame('y', $this->render('@if(in_array($x, [1, (2)]))y@endif', ['x' => 2]), 'balanced parentheses');
    }

    public function testLoops(): void
    {
        $this->assertSame('<1><2><3>', $this->render('@foreach($a as $i)<{{ $i }}>@endforeach', ['a' => [1, 2, 3]]));
        $this->assertSame('012', $this->render('@for($i = 0; $i < 3; $i++){{ $i }}@endfor'));
        $this->assertSame('123', $this->render('@php $i = 0; @endphp@while($i < 3){{ ++$i }}@endwhile'));
        $this->assertSame('13', $this->render('@foreach($a as $i)@if($i == 2)@continue@endif{{ $i }}@endforeach', ['a' => [1, 2, 3]]));
    }

    public function testForelseIncludingNesting(): void
    {
        $tpl = '@forelse($a as $i)[{{ $i }}]@empty none @endforelse';
        $this->assertSame('[1][2]', $this->render($tpl, ['a' => [1, 2]]));
        $this->assertSame(' none ', $this->render($tpl, ['a' => []]));
        $nested = '@forelse($o as $x)(@forelse($x as $y){{ $y }}@empty-@endforelse)@empty NONE @endforelse';
        $this->assertSame('(12)(-)', $this->render($nested, ['o' => [[1, 2], []]]));
    }

    public function testPhpBlockPassesThroughUntouched(): void
    {
        $this->assertSame('15', $this->render("@php\n\$x = 5 * 3;\n@endphp{{ \$x }}"));
    }

    public function testLayoutsSectionsYieldAndStacks(): void
    {
        $this->tpl('layouts/main', "<title>@yield('title', 'Default')</title><body>@yield('content')</body>@stack('scripts')");
        $this->tpl('page', "@extends('layouts/main')\n@section('title', 'A & B')\n@section('content')Hi {{ \$name }}@endsection\n@push('scripts')<s1>@endpush\n@push('scripts')<s2>@endpush");
        $out = $this->views->render('page', ['name' => '<b>']);
        $this->assertStringContainsString('<title>A &amp; B</title>', $out);
        $this->assertStringContainsString('<body>Hi &lt;b&gt;</body>', $out);
        $this->assertStringEndsWith('<s1><s2>', trim($out));

        $this->tpl('page2', "@extends('layouts/main')\n@section('content')x@endsection");
        $this->assertStringContainsString('<title>Default</title>', $this->views->render('page2'));
    }

    public function testIncludeInheritsParentDataAndMergesOverrides(): void
    {
        $this->tpl('partial', '[{{ $a }}|{{ $b }}]');
        $this->assertSame('[1|2]', $this->render("@include('partial', ['b' => 2])", ['a' => 1]));
        $this->assertSame('[&lt;|x]', $this->render("@include('partial', ['a' => '<', 'b' => 'x'])"));
    }

    public function testFormHelpersAndJson(): void
    {
        $this->assertSame('<input type="hidden" name="_method" value="DELETE">', $this->render("@method('delete')"));
        $out = $this->render('@json($d)', ['d' => ['a' => '</script><x>', 'b' => "'\"&"]]);
        $this->assertStringNotContainsString('</script>', $out);
        $this->assertStringNotContainsString("'", $out);
        $this->assertSame(['a' => '</script><x>', 'b' => "'\"&"], json_decode($out, true));
        $this->expectException(\InvalidArgumentException::class);
        $this->render("@method('GET')");
    }

    public function testNonDirectivesAreLeftAlone(): void
    {
        $css = '@media (max-width: 600px) { a { color: red } } mail me: dev@example.com @unknown(1)';
        $this->assertSame($css, $this->render($css));
        $this->assertSame('@if', $this->render('@@if'));
    }

    public function testEmailsWithDirectiveNamesAreNotCompiled(): void
    {
        $this->assertSame('x@include.com @if', $this->render('x@include.com @@if'));
        $this->assertSame('a@b.co yes', $this->render('a@b.co @if(true)yes@endif'));
    }

    public function testCompiledOutputIsCachedAndRefreshedOnChange(): void
    {
        $this->tpl('c', 'v1');
        $this->assertSame('v1', $this->views->render('c'));
        $files = glob($this->dir . '/cache/*.php');
        $this->assertCount(1, $files);

        $this->assertSame('v1', $this->views->render('c'));
        $this->assertSame($files, glob($this->dir . '/cache/*.php'), 'not recompiled when unchanged');

        $this->tpl('c', 'v2'); // same second: must still be picked up
        $this->assertSame('v2', $this->views->render('c'));
        $this->assertCount(1, glob($this->dir . '/cache/*.php'), 'superseded compiled file removed');
        $this->assertSame(1, $this->views->clearCache());
    }

    public function testProductionModeNeverRechecksSources(): void
    {
        $prod = new Factory($this->dir . '/views', $this->dir . '/cache', autoReload: false);
        $this->tpl('p', 'v1');
        $this->assertSame('v1', $prod->render('p'));
        $this->tpl('p', 'v2');
        $this->assertSame('v1', $prod->render('p'));
        $prod->clearCache();
        $this->assertSame('v2', $prod->render('p'));
    }

    public function testPlainPhpViewsStillWork(): void
    {
        file_put_contents($this->dir . '/views/old.php', '<?= $this->e($x) ?>');
        $this->assertSame('&lt;', $this->views->render('old', ['x' => '<']));
    }

    public function testExceptionInTemplateDoesNotLeakOutputBuffers(): void
    {
        $level = ob_get_level();
        try {
            $this->render("before @php throw new \\RuntimeException('boom'); @endphp");
            $this->fail();
        } catch (\RuntimeException) {
        }
        $this->assertSame($level, ob_get_level());
    }

    public function testCompilerOutput(): void
    {
        $php = (new Compiler())->compile('Hello {{ $name }} {!! $raw !!}');
        $this->assertStringContainsString('<?= e($name) ?>', $php);
        $this->assertStringContainsString('<?php echo $raw; ?>', $php);
    }

    public function testViewNamesCannotTraverse(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->views->render('../../etc/passwd');
    }
}
