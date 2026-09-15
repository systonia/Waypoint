<?php

namespace Waypoint\Tests\Unit\Attributes;

use PHPUnit\Framework\TestCase;
use Waypoint\Attributes\{Version, Sunset};

final class VersioningAttributesTest extends TestCase
{
    public function testVersionStoresItsValue(): void
    {
        $attr = new Version('v1');
        $this->assertSame('v1', $attr->value);
    }

    public function testVersionStripsLeadingAndTrailingSlashes(): void
    {
        $attr = new Version('/v1/');
        $this->assertSame('v1', $attr->value);
    }

    public function testSunsetStoresItsDate(): void
    {
        $attr = new Sunset(date: '2026-12-31');
        $this->assertSame('2026-12-31', $attr->date);
    }
}
