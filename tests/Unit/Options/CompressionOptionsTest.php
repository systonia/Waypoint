<?php

namespace Waypoint\Tests\Unit\Options;

use PHPUnit\Framework\TestCase;
use Waypoint\Options\CompressionOptions;

final class CompressionOptionsTest extends TestCase
{
    public function testEnabledDefaultsToTrue(): void
    {
        $opts = new CompressionOptions();
        $this->assertTrue($opts->enabled);
    }

    public function testMinBytesDefaultsTo1024(): void
    {
        $opts = new CompressionOptions();
        $this->assertSame(1024, $opts->minBytes);
    }

    public function testEnabledCanBeDisabled(): void
    {
        $opts = new CompressionOptions();
        $opts->enabled = false;
        $this->assertFalse($opts->enabled);
    }

    public function testMinBytesCanBeReconfigured(): void
    {
        $opts = new CompressionOptions();
        $opts->minBytes = 2048;
        $this->assertSame(2048, $opts->minBytes);
    }
}
