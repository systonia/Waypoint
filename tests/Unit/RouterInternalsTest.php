<?php

namespace Waypoint\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Waypoint\Waypoint;
use Waypoint\Routing\PropertyInjector;
use Waypoint\Http\View;
use Waypoint\Enums\RouteType;
use Waypoint\Tests\Fixtures\Managers\{MaintenanceManager, InjectionAwareManager};
use Waypoint\Tests\Fixtures\Support\ViewWithUnresolvableInject;

/**
 * Router/PropertyInjector edge cases the integration tests can't reach:
 * defensive guards for crafted/malformed task plans (Router::$tasks is
 * public, so these are planted directly) and injection without a container.
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

    public function testInjectReflectedDoesNothingWithoutAContainer(): void
    {
        // A Router built without a container leaves a View's #[Inject] properties unset.
        $view = new View('SomeView');

        (new PropertyInjector(null))->injectReflected($view);

        $envProp = new \ReflectionProperty($view, 'env');
        $this->assertFalse($envProp->isInitialized($view));
    }

    public function testInjectReflectedSkipsAnUnresolvableInjectProperty(): void
    {
        $app = Waypoint::create();
        $app->attach([]);

        $view = new ViewWithUnresolvableInject('SomeView');

        // One #[Inject] property whose type isn't a real class must not stop
        // View::$env (a resolvable one) on the same instance from being wired.
        (new PropertyInjector($app->getContainer()))->injectReflected($view);

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
