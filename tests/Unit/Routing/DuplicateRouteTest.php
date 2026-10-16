<?php

namespace Waypoint\Tests\Unit\Routing;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Waypoint\Attributes\{Controller, Get, Post};
use Waypoint\RouteCompiler;

#[Controller('/dup')]
final class DuplicateRouteTest_Static
{
    #[Get('/x')]
    public function one(): string { return 'one'; }

    #[Get('/x')]
    public function two(): string { return 'two'; }
}

#[Controller('/dup')]
final class DuplicateRouteTest_Dynamic
{
    #[Get('/{id}')]
    public function one(int $id): string { return 'one'; }
}

#[Controller('/dup')]
final class DuplicateRouteTest_DynamicAgain
{
    #[Get('/{id}')]
    public function two(int $id): string { return 'two'; }

    #[Post('/{id}')]
    public function post(int $id): string { return 'post'; }
}

final class DuplicateRouteTest extends TestCase
{
    public function testTheSameMethodAndPathTwiceFailsTheCompile(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate route GET /dup/x: ' . DuplicateRouteTest_Static::class . '::one() and ' . DuplicateRouteTest_Static::class . '::two().');
        (new RouteCompiler())->compile([DuplicateRouteTest_Static::class]);
    }

    public function testTheSameDynamicPatternTwiceFailsTheCompile(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate route GET /dup/{id}');
        (new RouteCompiler())->compile([DuplicateRouteTest_Dynamic::class, DuplicateRouteTest_DynamicAgain::class]);
    }

    public function testTheSamePathUnderAnotherMethodIsNoDuplicate(): void
    {
        $plans = (new RouteCompiler())->compile([DuplicateRouteTest_DynamicAgain::class]);
        $this->assertCount(1, $plans['dynamicRoutes']['POST']);
    }
}
