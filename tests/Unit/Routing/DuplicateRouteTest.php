<?php

namespace Waypoint\Tests\Unit\Routing;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Waypoint\RouteCompiler;
use Waypoint\Tests\Fixtures\Controllers\DuplicateRoutes\{StaticDuplicateController, DynamicDuplicateController, DynamicDuplicateAgainController};

final class DuplicateRouteTest extends TestCase
{
    public function testTheSameMethodAndPathTwiceFailsTheCompile(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate route GET /dup/x: ' . StaticDuplicateController::class . '::one() and ' . StaticDuplicateController::class . '::two().');
        (new RouteCompiler())->compile([StaticDuplicateController::class]);
    }

    public function testTheSameDynamicPatternTwiceFailsTheCompile(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate route GET /dup/{id}');
        (new RouteCompiler())->compile([DynamicDuplicateController::class, DynamicDuplicateAgainController::class]);
    }

    public function testTheSamePathUnderAnotherMethodIsNoDuplicate(): void
    {
        $plans = (new RouteCompiler())->compile([DynamicDuplicateAgainController::class]);
        $this->assertCount(1, $plans['dynamicRoutes']['POST']);
    }
}
