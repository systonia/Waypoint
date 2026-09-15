<?php

namespace Waypoint\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;
use Waypoint\{Waypoint, Router, RouteCompiler};
use Waypoint\Http\View;
use Waypoint\Enums\RouteType;
use Waypoint\Tests\Fixtures\Managers\{MaintenanceManager, InjectionAwareManager};
use Waypoint\Tests\Fixtures\Support\ViewWithUnresolvableInject;

/**
 * Targets Router internals that the "normal usage" integration tests can't
 * reach on their own: defensive guards for crafted/malformed task plans
 * (Router::$tasks is public, so these are constructed directly rather than
 * through normal compilation), and a couple of private helper methods
 * exercised via Reflection.
 */
final class RouterInternalsTest extends TestCase
{
    protected function setUp(): void
    {
        Waypoint::reset();
    }

    protected function tearDown(): void
    {
        Waypoint::reset();
    }

    public function testAddCompiledRouteRequiresAnExplicitRouteType(): void
    {
        $app = Waypoint::create();
        $app->attach([]);
        $router = $app->getRouter();

        $this->expectException(RuntimeException::class);
        $router->addCompiledRoute(['path' => '/x'], RouteType::Unset);
    }

    public function testFindRouteReturnsNullWhenNothingMatches(): void
    {
        $app = Waypoint::create();
        $app->attach([MaintenanceManager::class]);
        $router = $app->getRouter();

        $this->assertNull($router->findRoute($router->getRoutes(), 'GET', '/nope'));
    }

    public function testExtractTaskNameFallsBackToAPublicNameProperty(): void
    {
        // extractTaskName() is written to "defensively" support any
        // attribute-like object exposing ->getName(), a public $name, or
        // ->__toString() -- Task itself only ever exercises the first (its
        // own $name is private), so this proves the public-property
        // fallback actually works for a shape that would use it. Lives on
        // RouteCompiler (the reflection-based attribute-to-plan compiler),
        // not Router itself.
        $compiler = new RouteCompiler();

        $fakeTaskAttr = new class {
            public string $name = 'from-public-property';
        };

        $method = new ReflectionMethod($compiler, 'extractTaskName');

        $this->assertSame('from-public-property', $method->invoke($compiler, $fakeTaskAttr));
    }

    public function testExecuteTaskSkipsNonArrayEntriesWhileScanningForAShortNameMatch(): void
    {
        $app = Waypoint::create();
        $app->attach([MaintenanceManager::class]);
        $router = $app->getRouter();

        // A malformed entry that must not break the fallback scan for
        // other, unrelated task name lookups.
        $router->tasks['bogus'] = 'not-an-array';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Task 'nothing-matches-this' not found.");
        $router->executeTask('nothing-matches-this');
    }

    public function testExecuteTaskThrowsWhenTheResolvedHandlerMethodDoesNotExist(): void
    {
        $app = Waypoint::create();
        $app->attach([MaintenanceManager::class]);
        $router = $app->getRouter();

        $router->tasks['broken'] = [
            'name' => 'broken',
            'fullName' => 'broken',
            'manager' => MaintenanceManager::class,
            'method' => 'thisMethodDoesNotExist',
            'propInject' => [],
        ];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Task handler for 'broken' is invalid.");
        $router->executeTask('broken');
    }

    public function testInjectTaskPropertiesSkipsAnEntryWithNoPropertyName(): void
    {
        $app = Waypoint::create();
        $app->attach([MaintenanceManager::class]);
        $router = $app->getRouter();

        $router->tasks['malformed-prop'] = [
            'name' => 'malformed-prop',
            'fullName' => 'malformed-prop',
            'manager' => MaintenanceManager::class,
            'method' => 'ping',
            'propInject' => [['name' => null, 'type' => 'SomeType']],
        ];

        // Must not crash on the nameless propInject entry.
        $this->assertSame('pong:0', $router->executeTask('malformed-prop'));
    }

    public function testTaskPropertyInjectionSkipsRequestButInjectsTheRouterItself(): void
    {
        $app = Waypoint::create();
        $app->attach([InjectionAwareManager::class]);
        $router = $app->getRouter();

        $result = $router->executeTask('injection-aware:check');

        $this->assertFalse($result['hasRequest']);
        $this->assertTrue($result['hasRouter']);
    }

    public function testInjectViewPropertiesDoesNothingWithoutAContainer(): void
    {
        // A bare Router (no container passed) -- injectViewProperties()
        // must tolerate this and simply leave #[Inject] properties unset,
        // same as injectControllerProperties()'s default branch does.
        $router = new Router([]);
        $view = new View('SomeView');

        $method = new ReflectionMethod($router, 'injectViewProperties');
        $method->invoke($router, $view);

        $envProp = new \ReflectionProperty($view, 'env');
        $this->assertFalse($envProp->isInitialized($view));
    }

    public function testInjectViewPropertiesSkipsAnUnresolvableInjectProperty(): void
    {
        $app = Waypoint::create();
        $app->attach([]);
        $router = $app->getRouter();

        $view = new ViewWithUnresolvableInject('SomeView');

        $method = new ReflectionMethod($router, 'injectViewProperties');

        // Must not crash just because one #[Inject] property's type isn't
        // a real class -- View::$env (a real, resolvable type) on the same
        // instance still gets wired up normally.
        $method->invoke($router, $view);

        $envProp = new \ReflectionProperty($view, 'env');
        $this->assertInstanceOf(\Waypoint\Environment::class, $envProp->getValue($view));
    }

    public function testExtractFormatterOnlyFindsAFormatterOnATaskMethod(): void
    {
        $app = Waypoint::create();
        $app->attach([MaintenanceManager::class]);
        $router = $app->getRouter();

        $this->assertSame(
            'Waypoint\Attributes\JSONFormatter',
            $router->tasks['maintenance:ping']['formatter']['type']
        );
    }
}
