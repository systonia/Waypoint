<?php

namespace Waypoint\Tests\Unit\View;

use PHPUnit\Framework\TestCase;
use Waypoint\Environment;
use Waypoint\Html;
use Waypoint\Http\View;
use Waypoint\View\Editor;

/** A project of its own in the temp directory: views/ is where its views are. */
final class EditorTest extends TestCase
{
    private static string $root;

    public static function setUpBeforeClass(): void
    {
        self::$root = (string) realpath(sys_get_temp_dir()) . '/waypoint-editor-test-' . bin2hex(random_bytes(4));
        mkdir(self::$root . '/views', 0777, true);
        mkdir(self::$root . '/src');
    }

    public static function tearDownAfterClass(): void
    {
        rmdir(self::$root . '/views');
        rmdir(self::$root . '/src');
        rmdir(self::$root);
    }

    private function view(): string
    {
        return self::$root . '/views/Page.php';
    }

    public function testThisInAViewIsTheView(): void
    {
        $editor = new Editor(self::$root);
        $this->assertSame(View::class, $editor->thisClass($this->view()));
        $this->assertNull($editor->thisClass(self::$root . '/src/Page.php'));
        $this->assertSame([], $editor->diagnostics(self::$root . '/src/Page.php', '@if ('));
    }

    public function testModelIsTheClassModelNames(): void
    {
        $editor = new Editor(self::$root);
        $this->assertSame(['model' => Environment::class], $editor->variables($this->view(), "@use (Waypoint\\Environment as Env)\n@model (?Env)\n{{ \$model-> }}"));
        $this->assertSame([], $editor->variables($this->view(), "@model (Nope)\n{{ \$model-> }}"));
        $this->assertSame([], $editor->variables(self::$root . '/src/Page.php', '@model (Waypoint\\Html)'));
        // F12 on the class @model names
        $this->assertSame((new \ReflectionClass(Html::class))->getStartLine(), $editor->definition($this->view(), '@model (Waypoint\\Html)', 18)['line'] ?? null);
    }

    public function testProblemsTokensAndImports(): void
    {
        $editor = new Editor(self::$root);
        $this->assertSame([['line' => 2, 'message' => '@if has no @endif', 'severity' => 'error']], $editor->diagnostics($this->view(), "<p>\n@if (\$a)"));
        $this->assertSame(['' => '', 'Markup' => 'Waypoint\\Html'], $editor->imports($this->view(), '@use (Waypoint\\Html as Markup)'));
        $this->assertSame([
            ['line' => 1, 'column' => 0, 'length' => 8, 'type' => 'keyword'],
            ['line' => 1, 'column' => 9, 'length' => 7, 'type' => 'string'],
        ], $editor->tokens($this->view(), "@section('title')"));
    }
}
