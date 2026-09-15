<?php

namespace Waypoint\Tests\Integration;

use InvalidArgumentException;
use Waypoint\Waypoint;
use Waypoint\Options\{JWTOptions, RendererOptions, FileSystemOptions};
use Waypoint\Tests\Fixtures\Controllers\{CustomersController, NestedInjectionController};
use Waypoint\Tests\Fixtures\Services\{ExampleService, AnotherService, ConstructorInjectedService, PlainConstructorInjectedService, ServiceWithInjectedDependency, ServiceWithInjectedOptions};

final class AppBootstrapTest extends IntegrationTestCase
{
    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = "$dir/$entry";
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    public function testAttachExposesRoutesFromTheGivenControllers(): void
    {
        $app = Waypoint::create();
        $app->attach([CustomersController::class]);

        $routes = $app->getRouter()->getRoutes();

        $this->assertNotEmpty($routes);
        $this->assertNotNull($app->getRouter()->findRoute($routes, 'GET', '/customers'));
        $this->assertNotNull($app->getRouter()->findRoute($routes, 'GET', '/customers/{customerId}/orders'));
    }

    public function testConfigureResolvesAnOptionsClassFromTheContainer(): void
    {
        $app = Waypoint::create();

        $app->configure(function (RendererOptions $opts) {
            $opts->layout = 'CustomLayout.php';
        });

        $this->assertSame('CustomLayout.php', $app->getContainer()->get(RendererOptions::class)->layout);
    }

    public function testConfigureRejectsBuiltinTypedParameters(): void
    {
        $app = Waypoint::create();

        $this->expectException(InvalidArgumentException::class);
        $app->configure(function (string $notAllowed) {});
    }

    public function testConfigureRejectsUntypedParameters(): void
    {
        $app = Waypoint::create();

        $this->expectException(InvalidArgumentException::class);
        $app->configure(function ($notTyped) {});
    }

    public function testGetReturnsServiceInstanceWhenResolvable(): void
    {
        $app = Waypoint::create();
        $app->getContainer()->set(new ExampleService());

        $this->assertInstanceOf(ExampleService::class, $app->get(ExampleService::class));
    }

    public function testGetReturnsNullInsteadOfThrowingForUnresolvableClass(): void
    {
        $app = Waypoint::create();

        $this->assertNull($app->get('TotallyUnknownClass'));
    }

    public function testWaypointCreateReturnsTheSameInstanceUntilReset(): void
    {
        $first = Waypoint::create();
        $second = Waypoint::create();

        $this->assertSame($first, $second);

        Waypoint::reset();
        $this->assertNull(Waypoint::getInstance());

        $third = Waypoint::create();
        $this->assertNotSame($first, $third);
    }

    public function testAttachWalksInjectAttributesOnAPromotedConstructorPropertyToo(): void
    {
        // #[Inject] on a *promoted* constructor property is visible on both
        // the property and the parameter reflection surfaces. The
        // constructor itself still never receives a resolved argument
        // (ConstructorInjectedService is always constructed as `new
        // ConstructorInjectedService()`, $example null via its own
        // default) -- but since a promoted property IS a real property,
        // App's post-construction #[Inject] wiring pass finds and resolves
        // it same as any other property, same as it would if $example had
        // been declared the ordinary way instead of via promotion.
        $app = Waypoint::create();
        $app->attach([ConstructorInjectedService::class]);

        $this->assertTrue($app->getContainer()->has(ExampleService::class));
        $this->assertInstanceOf(
            ExampleService::class,
            $app->getContainer()->get(ConstructorInjectedService::class)->example,
        );
    }

    public function testAttachWalksInjectAttributesOnAPlainConstructorParameterToo(): void
    {
        // $dep is a plain, non-promoted parameter -- no matching property
        // exists for the property walk to find first, so this is the one
        // path that actually exercises the constructor-parameter walk.
        $app = Waypoint::create();
        $app->attach([PlainConstructorInjectedService::class]);

        $this->assertTrue($app->getContainer()->has(AnotherService::class));
        $this->assertNull($app->getContainer()->get(PlainConstructorInjectedService::class)->captured);
    }

    public function testAttachWiresInjectPropertiesOnServicesToo(): void
    {
        // Regression test: initDependencyInjection() used to construct
        // every discovered class with `new $cls()` and stop there --
        // #[Inject] on a controller or #[Middleware] class worked (Router
        // applies that wiring itself, per request, via
        // injectControllerProperties()), but #[Inject] on a plain
        // container-managed SERVICE class (e.g. a repository injecting a
        // shared DB connection service) was silently never applied: the
        // property stayed uninitialized, and reading it threw. This is
        // fixed by a second pass over every discovered instance, run only
        // after all of them are already registered in the container, so
        // it works regardless of discovery order.
        $app = Waypoint::create();
        $app->attach([NestedInjectionController::class]);

        $service = $app->getContainer()->get(ServiceWithInjectedDependency::class);
        $this->assertInstanceOf(AnotherService::class, $service->getOther());
    }

    public function testAttachDoesNotClobberAnOptionsInstanceConfiguredBeforeAttach(): void
    {
        // Regression test: initDependencyInjection() used to construct
        // *every* discovered class with `new $cls()`, including one that
        // was already registered in the container -- so an Options class
        // configured via configure() (before attach()) got silently
        // overwritten with a fresh, unconfigured instance the moment
        // anything else #[Inject]ed it too. Container::isRegistered() is
        // what initDependencyInjection() now checks first, to reuse the
        // already-configured instance instead of replacing it.
        $tempDir = sys_get_temp_dir() . '/test-' . bin2hex(random_bytes(6));
        try {
            $app = Waypoint::create();
            $app->configure(function (FileSystemOptions $fs) use ($tempDir) {
                $fs->cacheDirectory = $tempDir;
            });
            $app->attach([ServiceWithInjectedOptions::class]);

            $this->assertSame(
                $tempDir,
                $app->getContainer()->get(FileSystemOptions::class)->cacheDirectory,
            );

            $service = $app->getContainer()->get(ServiceWithInjectedOptions::class);
            $this->assertSame(
                $tempDir,
                $service->getFileSystemOptions()?->cacheDirectory,
            );
        } finally {
            $this->removeDirectory($tempDir);
        }
    }

    public function testAttachSkipsControllersThatDoNotExist(): void
    {
        // discoverAllClasses()/initDependencyInjection() must tolerate a
        // bad class name (e.g. a typo) instead of fataling on it.
        $app = Waypoint::create();
        $app->attach(['Waypoint\Tests\Fixtures\Controllers\DoesNotExistController', CustomersController::class]);

        $this->assertNotEmpty($app->getRouter()->getRoutes());
    }

    public function testAttachInTrustModeReusesTheCachedServiceListInsteadOfDiscovering(): void
    {
        $tempDir = sys_get_temp_dir() . '/test-' . bin2hex(random_bytes(6));
        try {
            // First boot: real discovery, populates the cache (including
            // the 'services' list PlainConstructorInjectedService's
            // constructor-parameter #[Inject] pulls in AnotherService).
            $first = Waypoint::create();
            $first->configure(function (FileSystemOptions $fs) use ($tempDir) {
                $fs->cacheDirectory = $tempDir;
            });
            $first->attach([PlainConstructorInjectedService::class]);
            $this->assertTrue($first->getContainer()->has(AnotherService::class));

            // Second boot, trust mode on: must land in the same state by
            // reusing the cached service list, without re-discovering.
            Waypoint::reset();
            $second = Waypoint::create();
            $second->configure(function (FileSystemOptions $fs) use ($tempDir) {
                $fs->cacheDirectory = $tempDir;
                $fs->cacheValidate = false;
            });
            $second->attach([PlainConstructorInjectedService::class]);

            $this->assertTrue($second->getContainer()->has(AnotherService::class));
        } finally {
            $this->removeDirectory($tempDir);
        }
    }

    public function testAttachInTrustModeFallsBackToDiscoveryOnFirstBoot(): void
    {
        $tempDir = sys_get_temp_dir() . '/test-' . bin2hex(random_bytes(6));
        try {
            // Trust mode, but nothing cached yet -- must still work.
            $app = Waypoint::create();
            $app->configure(function (FileSystemOptions $fs) use ($tempDir) {
                $fs->cacheDirectory = $tempDir;
                $fs->cacheValidate = false;
            });
            $app->attach([PlainConstructorInjectedService::class]);

            $this->assertTrue($app->getContainer()->has(AnotherService::class));
        } finally {
            $this->removeDirectory($tempDir);
        }
    }

    public function testGetConfigDelegatesToTheContainer(): void
    {
        $app = Waypoint::create();
        $app->configure(function (JWTOptions $opts) {
            $opts->secret = 'abc';
        });

        $this->assertSame('abc', Waypoint::getConfig(JWTOptions::class)->secret);
    }
}
