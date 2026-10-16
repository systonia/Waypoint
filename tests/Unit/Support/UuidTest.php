<?php

namespace Waypoint\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use Waypoint\Support\Uuid;

final class UuidTest extends TestCase
{
    public function testV4IsARandomVersion4Uuid(): void
    {
        $uuid = Uuid::v4();

        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $uuid);
        $this->assertNotSame($uuid, Uuid::v4());
    }
}
