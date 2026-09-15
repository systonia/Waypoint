<?php

namespace Waypoint\Tests\Unit\Enums;

use PHPUnit\Framework\TestCase;
use Waypoint\Enums\RouteType;

final class RouteTypeEnumTest extends TestCase
{
    public function testCaseValues(): void
    {
        $this->assertSame('', RouteType::Unset->value);
        $this->assertSame('static', RouteType::Static->value);
        $this->assertSame('dynamic', RouteType::Dynamic->value);
        $this->assertSame('task', RouteType::Task->value);
    }

    public function testAllCasesArePresent(): void
    {
        $names = array_map(fn(RouteType $c) => $c->name, RouteType::cases());
        $this->assertEqualsCanonicalizing(['Unset', 'Static', 'Dynamic', 'Task'], $names);
    }
}
