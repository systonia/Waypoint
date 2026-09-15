<?php

namespace Waypoint\Tests\Unit\Options;

use PHPUnit\Framework\TestCase;
use Waypoint\Options\RendererOptions;

final class RendererOptionsTest extends TestCase
{
    private ?string $originalScriptFilename;

    protected function setUp(): void
    {
        $this->originalScriptFilename = $_SERVER['SCRIPT_FILENAME'] ?? null;
        $_SERVER['SCRIPT_FILENAME'] = __FILE__;
    }

    protected function tearDown(): void
    {
        if ($this->originalScriptFilename !== null) {
            $_SERVER['SCRIPT_FILENAME'] = $this->originalScriptFilename;
        } else {
            unset($_SERVER['SCRIPT_FILENAME']);
        }
    }

    public function testDefaultLayoutIsUnderscoreLayout(): void
    {
        $opts = new RendererOptions();
        $this->assertSame('_Layout', $opts->layout);
    }

    public function testRelativeDirectoryIsResolvedAgainstScriptDirectory(): void
    {
        $opts = new RendererOptions(['directory' => 'templates']);
        $this->assertSame(dirname(realpath(__FILE__)) . '/templates', $opts->directory);
    }

    public function testMissingDirectoryDefaultsToScriptDirectory(): void
    {
        // The property's own set-hook re-normalizes the value on assignment,
        // which strips the trailing slash the empty-string default would
        // otherwise leave behind.
        $opts = new RendererOptions();
        $this->assertSame(dirname(realpath(__FILE__)), $opts->directory);
    }

    public function testCustomLayoutIsAssigned(): void
    {
        $opts = new RendererOptions(['layout' => 'MyLayout']);
        $this->assertSame('MyLayout', $opts->layout);
    }

    public function testToArrayOmitsNothingWhenBothAreSet(): void
    {
        $opts = new RendererOptions(['directory' => 'views', 'layout' => 'Main']);
        $arr = $opts->toArray();

        $this->assertSame(dirname(realpath(__FILE__)) . '/views', $arr['directory']);
        $this->assertSame('Main', $arr['layout']);
    }
}
