<?php

namespace Waypoint\Tests\Unit\View;

use PHPUnit\Framework\TestCase;
use Waypoint\Http\View;
use Waypoint\View\Compiler;

final class CompilerTest extends TestCase
{
    /** @return list<string> */
    private static function problems(string $source): array
    {
        return array_map(static fn (array $p) => "{$p['line']}: {$p['message']}", (new Compiler($source))->problems);
    }

    public function testDirectivesBecomePhpOnTheSameLines(): void
    {
        $source = "@foreach (\$items as \$item)\n  {{ \$item }}\n  @if (\$a === ')')\n  @elseif (\$b)\n  @else\n  @endif\n@endforeach\n{{/* one\ntwo */}}";
        $php = (new Compiler($source))->compile();
        $this->assertSame(
            "<?php foreach (\$items as \$item): \n  ?><?=  \$item  ?><?php\n   if (\$a === ')'): \n  ?><?php elseif (\$b): \n  ?><?php else: \n  ?><?php endif \n?><?php endforeach \n?><?php /* one\ntwo */ ?>",
            $php,
        );
        $this->assertSame(substr_count($source, "\n"), substr_count($php, "\n"));
    }

    public function testTextAndPhpStayAsTheyAre(): void
    {
        foreach (['mail@example.com', '<style>@media (max-width: 9px) { a { b: c } }</style>', '<?php $a = "{{ x }}"; ?>', '<?= "@if (1)" ?>'] as $source) {
            $this->assertSame($source, (new Compiler($source))->compile(), $source);
            $this->assertSame([], self::problems($source), $source);
        }
        $this->assertSame('@if and @', (new Compiler('@@if and @@'))->compile());
    }

    public function testADirectiveMayFollowAnEcho(): void
    {
        $this->assertSame('<?php if ($a): ?><?=  $b  ?><?php endif ?>', (new Compiler('@if ($a){{ $b }}@endif'))->compile());
    }

    public function testSectionsAndYield(): void
    {
        $this->assertSame(
            "<?php \$this->startSection('title') \n?>Shop<?php\n \$this->endSection() \n?><title><?= \$this->section('title') ?></title>",
            (new Compiler("@section('title')\nShop\n@endsection\n<title>@yield('title')</title>"))->compile(),
        );
        $this->assertSame(['1: @section has no @endsection'], self::problems("@section('a')\n"));
        $this->assertSame(['1: @endsection without @section'], self::problems('@endsection'));
        $this->assertSame(["1: @yield needs a name: @yield ('title')"], self::problems('@yield title'));
    }

    public function testModelNamesTheTypeAndChecksItWhereAParameterCould(): void
    {
        $compiler = new Compiler("@use (Waypoint\\Html as Markup)\n@model (?Markup)\n<p>");
        $this->assertSame('?Markup', $compiler->model);
        $this->assertSame("<?php \n?><?php \$this->assertModel(static fn (?Markup \$model) => null, '?Markup') \n?><p>", $compiler->compile());
        // A PHPDoc type is for PHPStan and the editor only
        $this->assertSame('<?php ?>', (new Compiler('@model (list<Waypoint\\Html>)'))->compile());
        $this->assertSame('<?php ?>', (new Compiler('@model (array{id: int})'))->compile());
        $this->assertSame(["1: @model (App\\Missing): there is no class or interface App\\Missing"], self::problems('@model (App\\Missing)'));
        $this->assertSame(['2: @model (int): a view has one model, @model (string) is above already'], self::problems("@model (string)\n@model (int)"));
        $this->assertSame(['1: @model needs a type: @model (App\\Models\\Product)'], self::problems('@model App'));
    }

    public function testMistakesAreReportedAtTheirLine(): void
    {
        $this->assertSame(['2: @forech is no directive and stays text. Did you mean @foreach?'], self::problems("<p>\n@forech (\$a as \$b)"));
        $this->assertSame(['1: @if ( has no )'], self::problems('@if ($a === "(" '));
        $this->assertSame(['2: {{ has no }}'], self::problems("\n{{ \$a "));
        $this->assertSame(['1: {{ }} has a /* without */: it would comment out the view up to the next */'], self::problems('<p>{{ $a /* euros }}</p>{{/* b */}}'));
        $this->assertSame(['3: @endforeach without @foreach: the @if of line 2 is still open'], self::problems("@foreach (\$a as \$b)\n@if (\$c)\n@endforeach\n"));
        $this->assertSame(['2: @code has no @endcode'], self::problems("<p>\n@code \$a = 1;\n"));
    }

    public function testAViewRefusesWhatWouldFailOnItsSecondRenderAndTakesUse(): void
    {
        $this->assertSame([
            '1: namespace in a view: a view has none, and @use (App\\Status) imports a class',
            '2: A named function in a view is declared again on the next render, which PHP refuses: use a closure',
            '3: break outside a loop: PHP refuses it before the view runs',
            '4: $this is the view: a view cannot assign or unset it',
        ], self::problems("<?php namespace App; ?>\n@code function f() {} @endcode\n<?php break ?>\n<?php \$this = null ?>"));
        $this->assertSame([], self::problems('<?php use Waypoint\\Html; ?>'));
    }

    public function testConditionalClassesUseWaypointClasses(): void
    {
        $this->assertSame(
            '<li class="<?php ob_start() ?>row<?php echo \\Waypoint\\classes((string) ob_get_clean(), [($a) ? \'late\' : \'\']) ?>">',
            (new Compiler('<li class="row" @class.late="$a">'))->compile(),
        );
    }

    public function testACompiledViewImportsItsUseAndClassesOnItsFirstLine(): void
    {
        $php = View::compile("@use (Waypoint\\Html)\n<p>{{ classes('a', ['b' => true]) }} {{ new Html('<i>') }}</p>", '/v.php');
        $this->assertStringStartsWith("<?php use function Waypoint\\classes; use Waypoint\\Html; ?><?php \n?><p>", $php);
        ob_start();
        eval('?>' . $php);
        $this->assertSame('<p>a b <i></p>', ob_get_clean());
    }

    public function testHeaderLeavesOutWhatPhpWouldWarnAbout(): void
    {
        $this->assertSame('use Waypoint\\Html as Markup; ', Compiler::header('', [['class', 'DateTime', 'DateTime'], ['class', 'Waypoint\\Html', 'Markup']]));
        $this->assertSame('namespace App; use DateTime; use function strlen as size; ', Compiler::header('App', [['class', 'DateTime', 'DateTime'], ['function', 'strlen', 'size']]));
    }

    public function testADirectiveOnItsOwnLineLeavesNoWhitespaceBehind(): void
    {
        $cases = [
            // A section's content without its indentation and line breaks
            ["@section('title')\n    {{ 'Orders' }}\n@endsection\n<title><?= \$this->sections['title'] ?></title>", '<title>Orders</title>'],
            ["<p>\n    @if (\$x)\n        <b>yes</b>\n    @elseif (\$x === 0)\n        zero\n    @else\n        <i>no</i>\n    @endif\n</p>", '<p><i>no</i></p>'],
            ["<ul>\n    @foreach ([1, 2] as \$i)\n        <li>{{ \$i }}</li>\n    @endforeach\n</ul>\n", "<ul><li>1</li><li>2</li></ul>\n"],
            ["<p>\n    @code\n        \$a = 'A';\n    @endcode\n    {{ \$a }}\n</p>", "<p>A\n</p>"],
            // Blank lines between directives go too
            ["@if (true)\n\n    @if (true)\n\n        x\n\n    @endif\n\n@endif\n\nend", 'xend'],
            // Without a line break the spaces stay as written
            ['Hello @if ($x === null) world @endif!', 'Hello  world !'],
            ['Hello @if ($x) world @endif!', 'Hello !'],
            ["a  \t@if (true)\t b @endif\t  c", "a  \t\t b \t  c"],
            // An echo keeps its line break when no directive takes it
            ["@if (true)\n    {{ 'a' }}\n    {{ 'b' }}\n@endif", "a\n    b"],
            ["a\r\n  @if (true)\r\n    b\r\n  @endif\r\nc", 'abc'],
            ["@use (Waypoint\\Html)\n@model (mixed)\n\n<p>", '<p>'],
            // Plain PHP tags stay as they are
            ["<?php \$y = 1 ?>\n  @if (true)\n<?= \$y ?>\n@endif", '1'],
        ];
        foreach ($cases as [$source, $expected]) {
            $compiled = (new Compiler($source))->compile();
            $this->assertSame(substr_count($source, "\n"), substr_count($compiled, "\n"), $source);
            $this->assertSame(substr_count($source, "\r"), substr_count($compiled, "\r"), $source);
            $this->sections = [];
            ob_start();
            $x = null;
            eval('?>' . $compiled);
            $this->assertSame($expected, (string) ob_get_clean(), $source);
        }
    }

    public function testTheWhitespaceGoesIntoTheDirectivesTag(): void
    {
        $this->assertSame(
            "<p><?php\n     if (\$x): \n        ?><b>yes</b><?php\n     endif \n?></p>",
            (new Compiler("<p>\n    @if (\$x)\n        <b>yes</b>\n    @endif\n</p>"))->compile(),
        );
    }

    public function testAnErrorInsideABlockKeepsItsLine(): void
    {
        $compiled = (new Compiler("<main>\n    @if (true)\n\n        <p>\n            {{ \$missing->name() }}\n        </p>\n    @endif\n</main>"))->compile();
        try {
            $missing = null;
            ob_start();
            eval('?>' . $compiled);
            $this->fail('No error');
        } catch (\Error $e) {
            $this->assertSame(5, $e->getLine());
        } finally {
            ob_end_clean();
        }
    }

    /** @var array<string, string> For the sections of the cases: what startSection() and endSection() take */
    private array $sections = [];

    private ?string $section = null;

    public function startSection(string $name): void
    {
        $this->section = $name;
        ob_start();
    }

    public function endSection(): void
    {
        $this->sections[(string) $this->section] = (string) ob_get_clean();
    }

    public function assertModel(\Closure $check, string $type): void
    {
    }
}
