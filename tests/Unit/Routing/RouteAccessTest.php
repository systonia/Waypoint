<?php

namespace Waypoint\Tests\Unit\Routing;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use stdClass;
use Waypoint\RouteCompiler;
use Waypoint\Tests\Fixtures\Controllers\{ClassRoleController, InheritedGuardsController};

final class RouteAccessTest extends TestCase
{
    public function testAClassWithoutGuardsNeedsNoSignIn(): void
    {
        $this->assertSame(
            ['authenticated' => false, 'role' => null, 'permissions' => null],
            RouteCompiler::access(new ReflectionClass(stdClass::class))
        );
    }

    public function testAGuardTwoClassesUpIsInherited(): void
    {
        $this->assertSame(
            ['authenticated' => true, 'role' => null, 'permissions' => null],
            RouteCompiler::access(new ReflectionClass(InheritedGuardsController::class))
        );
    }

    public function testTheMethodsRoleWinsOverTheClassesAndImpliesSignIn(): void
    {
        $class = new ReflectionClass(ClassRoleController::class);

        $this->assertSame(
            ['authenticated' => true, 'role' => 'editor', 'permissions' => null],
            RouteCompiler::access($class, $class->getMethod('plain'))
        );
        $this->assertSame('admin', RouteCompiler::access($class, $class->getMethod('override'))['role']);
    }
}
