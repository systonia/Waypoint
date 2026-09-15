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

    public function testMiddlewareBaseRunsBeforeThenNextThenAfterInOrder(): void
    {
        $output = $this->dispatch('GET', '/middleware/before-after');

        $this->assertSame(
            ['before-after:before', 'controller', 'before-after:after'],
            CallTracker::$calls
        );
        $this->assertSame(['ok' => true], json_decode($output, true));
    }

    public function testMiddlewareBaseSubclassCanSetResponseHeadersFromBothBeforeAndAfter(): void
    {
        // Response::send() only runs once, in App::handleHttp(), after
        // the entire chain (per-route #[Middleware(...)] included) has
        // unwound -- so a header set from either hook actually reaches
        // the client, not just before().
        $this->dispatch('GET', '/middleware/before-after');
        $headers = $this->sentHeaders();

        $this->assertSame('yes', $headers['x-before'] ?? null);
        $this->assertSame('yes', $headers['x-after'] ?? null);
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

    public function testMiddlewareNotExtendingMiddlewareBaseIsSkippedAtCompileTime(): void
    {
        // MiddlewareBase is mandatory now: an existing class with a
        // matching handle() signature but no MiddlewareBase parent must
        // be rejected the same way a nonexistent class is -- the
        // controller runs with no middleware wrapped around it, app boot
        // doesn't crash.
        $output = $this->dispatch('GET', '/middleware/not-middleware-base');

        $this->assertSame(['controller'], CallTracker::$calls);
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

    /** @return array<string, string> */
    private function sentHeaders(): array
    {
        $raw = function_exists('xdebug_get_headers') ? xdebug_get_headers() : headers_list();
        $headers = [];
        foreach ($raw as $line) {
            [$name, $value] = array_map('trim', explode(':', $line, 2) + [1 => '']);
            $headers[strtolower($name)] = $value;
        }
        return $headers;
    }
}
