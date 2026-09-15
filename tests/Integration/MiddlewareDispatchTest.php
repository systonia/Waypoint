<?php

namespace Waypoint\Tests\Integration;

use Waypoint\Waypoint;
use Waypoint\Tests\Fixtures\Controllers\ClassMiddlewareController;
use Waypoint\Tests\Fixtures\Controllers\MiddlewareController;
use Waypoint\Tests\Fixtures\Services\ExampleService;
use Waypoint\Tests\Fixtures\Support\CallTracker;

final class MiddlewareDispatchTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Waypoint::create()->attach([MiddlewareController::class, ClassMiddlewareController::class]);
    }

    public function testStackedMiddlewaresRunInDeclarationOrderBeforeTheController(): void
    {
        $output = $this->dispatch('GET', '/middleware/stacked');

        $this->assertSame(
            ['add-header', 'injecting:' . ExampleService::class, 'controller'],
            CallTracker::$calls
        );
        $this->assertSame(['ok' => true], json_decode($output, true));
    }

    public function testMiddlewareCanInjectItsOwnDependencies(): void
    {
        $this->dispatch('GET', '/middleware/stacked');

        $this->assertContains('injecting:' . ExampleService::class, CallTracker::$calls);
    }

    public function testShortCircuitingMiddlewarePreventsTheControllerFromRunning(): void
    {
        $output = $this->dispatch('GET', '/middleware/blocked');

        $this->assertSame(['short-circuit'], CallTracker::$calls);
        $this->assertSame(['error' => 'blocked by middleware'], json_decode($output, true));
    }

    public function testMiddlewareReferencingANonexistentClassIsSkippedAtCompileTime(): void
    {
        // Must not crash app boot; the controller just runs with no
        // middleware wrapped around it.
        $output = $this->dispatch('GET', '/middleware/bad-middleware');

        $this->assertSame(['ok' => true], json_decode($output, true));
    }

    public function testClassLevelMiddlewareRunsForEveryRouteOnTheController(): void
    {
        $output = $this->dispatch('GET', '/class-middleware/plain');

        $this->assertSame(['class-level', 'controller'], CallTracker::$calls);
        $this->assertSame(['ok' => true], json_decode($output, true));
    }

    public function testClassLevelMiddlewareRunsBeforeMethodLevelMiddleware(): void
    {
        $output = $this->dispatch('GET', '/class-middleware/stacked');

        $this->assertSame(['class-level', 'add-header', 'controller'], CallTracker::$calls);
        $this->assertSame(['ok' => true], json_decode($output, true));
    }

    public function testUnionTypedParameterCompilesWithoutCrashing(): void
    {
        // Not dispatched: Router's 'Unknown' binding always passes null,
        // which would TypeError against this method's non-nullable union
        // parameter -- the point here is only that *compiling* the route
        // (buildArgPlan() can't map string|int to one builtin type) doesn't
        // crash App::attach() itself.
        $routes = Waypoint::create()->getRouter()->getRoutes();

        $this->assertNotNull(
            Waypoint::create()->getRouter()->findRoute($routes, 'GET', '/middleware/union-param')
        );
    }
}
