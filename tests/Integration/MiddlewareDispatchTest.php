<?php

namespace Waypoint\Tests\Integration;

use InvalidArgumentException;
use Waypoint\RouteCompiler;
use Waypoint\Waypoint;
use Waypoint\Tests\Fixtures\Controllers\ClassMiddlewareController;
use Waypoint\Tests\Fixtures\Controllers\MiddlewareController;
use Waypoint\Tests\Fixtures\Controllers\NotMiddlewareBaseController;
use Waypoint\Tests\Fixtures\Controllers\UnknownMiddlewareController;
use Waypoint\Tests\Fixtures\DTO\CreateProductDTO;
use Waypoint\Tests\Fixtures\Middlewares\InjectingMiddleware;
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

    public function testAppLevelMiddlewareRegisteredViaUseGetsInjectPropertiesWiredToo(): void
    {
        // Unlike a per-route #[Middleware(...)] class, an instance passed
        // to $app->use() never goes through Router::resolveController()/
        // injectControllerProperties(). App::use() wires #[Inject]
        // itself (injectServiceProperties(), the same pass attach() runs
        // for eagerly-constructed services), or this would stay unset.
        Waypoint::create()->use(new InjectingMiddleware());

        $this->dispatch('GET', '/class-middleware/plain');

        $this->assertContains('injecting:' . ExampleService::class, CallTracker::$calls);
    }

    public function testMiddlewareAddedAfterARequestRunsOnTheNextOne(): void
    {
        $this->dispatch('GET', '/class-middleware/plain');
        Waypoint::create()->use(function ($req, $res, callable $next): mixed {
            CallTracker::$calls[] = 'added-late';
            return $next($req, $res);
        });

        $this->dispatch('GET', '/class-middleware/plain');

        $this->assertContains('added-late', CallTracker::$calls);
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
        // unwound, so a header set from either hook actually reaches
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

    public function testMiddlewareReferencingANonexistentClassFailsTheCompile(): void
    {
        // Skipping it would run the route without the check it was declared to have.
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Totally\\Fake\\MiddlewareClass');
        (new RouteCompiler())->compile([UnknownMiddlewareController::class]);
    }

    public function testMiddlewareNotExtendingMiddlewareBaseFailsTheCompile(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(NotMiddlewareBaseController::class);
        (new RouteCompiler())->compile([NotMiddlewareBaseController::class]);
    }

    public function testRequestBodyDtoIsSetBeforeAnAfterHookRuns(): void
    {
        $output = $this->dispatch('POST', '/middleware/with-body-dto', [], ['name' => 'Widget', 'sku' => 'ABC123']);

        $this->assertSame(
            ['controller', 'bodyDto:' . CreateProductDTO::class],
            CallTracker::$calls
        );
        $this->assertSame(['name' => 'Widget'], json_decode($output, true));
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
        // buildArgPlan() can't map string|int|null to one builtin type: the
        // route binds null, which the nullable parameter takes. A non-nullable
        // one fails the compile (see ArgumentBindingTest).
        $routes = Waypoint::create()->getRouter()->getRoutes();

        $this->assertNotNull(
            Waypoint::create()->getRouter()->findRoute($routes, 'GET', '/middleware/union-param')
        );
    }

}
