<?php

namespace Waypoint\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use Waypoint\{Router, Waypoint};
use Waypoint\Options\{FileSystemOptions, LoggerOptions};
use Waypoint\Tests\Fixtures\Controllers\{
    CustomersController,
    VersionedWidgetsController,
    UsersV1Controller,
    UsersV2Controller,
    UsersV10Controller,
    DeprecatedRoutesController,
    DeprecatedClassController,
    InvalidSunsetController
};
use Waypoint\Tests\Fixtures\Support\DummyLogger;

/**
 * Inspects the compiled route plan directly (Router::$staticRoutes), rather
 * than dispatching a real request -- #[Version]/#[Sunset]/native
 * #[\Deprecated] are all resolved once at compile time (RouteCompiler), so
 * this is enough to prove that resolution is correct without ever actually
 * invoking a controller method (which would, for the native #[\Deprecated]
 * fixtures, trigger a real PHP deprecation notice just by being called --
 * see RouteVersioningDispatchTest for that end-to-end path instead).
 */
final class RouteVersioningTest extends IntegrationTestCase
{
    private function attachAndGetRoutes(array $controllers): array
    {
        Waypoint::create()->attach($controllers);
        return Waypoint::getRouter()->staticRoutes;
    }

    public function testAnUnversionedRouteKeepsItsPlainPathAndHasNoVersion(): void
    {
        $routes = $this->attachAndGetRoutes([CustomersController::class]);

        $this->assertArrayHasKey('/customers', $routes['GET']);
        $this->assertNull($routes['GET']['/customers']['version']);
        $this->assertSame('/customers', $routes['GET']['/customers']['unversionedPath']);
    }

    public function testClassLevelVersionPrefixesThePathAndIsReportedAsTheEffectiveVersion(): void
    {
        $routes = $this->attachAndGetRoutes([VersionedWidgetsController::class]);

        $this->assertArrayHasKey('/v1/widgets/list', $routes['GET']);
        $this->assertSame('v1', $routes['GET']['/v1/widgets/list']['version']);
        $this->assertSame('/widgets/list', $routes['GET']['/v1/widgets/list']['unversionedPath']);
        // The unprefixed path must not also exist as its own route.
        $this->assertArrayNotHasKey('/widgets/list', $routes['GET']);
    }

    public function testMethodLevelVersionOverridesTheClassLevelVersion(): void
    {
        $routes = $this->attachAndGetRoutes([VersionedWidgetsController::class]);

        $this->assertArrayHasKey('/v2/widgets/override', $routes['GET']);
        $this->assertSame('v2', $routes['GET']['/v2/widgets/override']['version']);
        $this->assertArrayNotHasKey('/v1/widgets/override', $routes['GET']);
    }

    public function testThreeVersionsOfTheSameEndpointCompileToThreeDistinctPaths(): void
    {
        $routes = $this->attachAndGetRoutes([
            UsersV1Controller::class,
            UsersV2Controller::class,
            UsersV10Controller::class,
        ]);

        foreach (['v1', 'v2', 'v10'] as $version) {
            $path = "/$version/users";
            $this->assertArrayHasKey($path, $routes['GET'], "expected $path to be compiled");
            $this->assertSame($version, $routes['GET'][$path]['version']);
            $this->assertSame('/users', $routes['GET'][$path]['unversionedPath']);
        }
    }

    public function testNativeDeprecatedAttributeAtMethodLevelIsDetected(): void
    {
        $routes = $this->attachAndGetRoutes([DeprecatedRoutesController::class]);

        $this->assertTrue($routes['GET']['/legacy/method-level']['deprecated']);
        $this->assertFalse($routes['GET']['/legacy/not-deprecated']['deprecated']);
    }

    public function testSunsetAtTheClassLevelAppliesToEveryRouteOnIt(): void
    {
        // Unlike #[Version]/#[Sunset], native #[\Deprecated] (PHP 8.4+)
        // cannot target a class at all -- only functions/methods -- so
        // there's no class-level-deprecated scenario to test; this covers
        // #[Sunset]'s own class-level override instead.
        $routes = $this->attachAndGetRoutes([DeprecatedClassController::class]);

        $expected = (new DateTimeImmutable('2099-06-15', new DateTimeZone('UTC')))
            ->format('D, d M Y H:i:s \G\M\T');

        $this->assertFalse($routes['GET']['/legacy2/class-level']['deprecated']);
        $this->assertSame($expected, $routes['GET']['/legacy2/class-level']['sunsetHeader']);
    }

    public function testSunsetDateIsCompiledIntoAnRfc8594HttpDateHeaderValue(): void
    {
        $routes = $this->attachAndGetRoutes([DeprecatedRoutesController::class]);

        $expected = (new DateTimeImmutable('2099-12-31', new DateTimeZone('UTC')))
            ->format('D, d M Y H:i:s \G\M\T');

        $this->assertSame($expected, $routes['GET']['/legacy/method-level']['sunsetHeader']);
    }

    public function testARouteWithoutSunsetHasNoSunsetHeader(): void
    {
        $routes = $this->attachAndGetRoutes([DeprecatedRoutesController::class]);

        $this->assertNull($routes['GET']['/legacy/not-deprecated']['sunsetHeader']);
    }

    public function testAMalformedSunsetDateDegradesToNoHeaderInsteadOfCrashing(): void
    {
        $routes = $this->attachAndGetRoutes([InvalidSunsetController::class]);

        $this->assertNull($routes['GET']['/bad-sunset/route']['sunsetHeader']);
    }

    public function testCompilingAnUnversionedRouteLogsAWarning(): void
    {
        $dummy = new DummyLogger();
        $app = Waypoint::create();
        $app->configure(function (LoggerOptions $opts) use ($dummy) {
            $opts->add($dummy);
        });
        $app->attach([CustomersController::class]);

        $messages = array_column($dummy->logs, 1);
        $this->assertNotEmpty(array_filter($messages, fn($m) => str_contains($m, 'GET /customers')));
    }

    public function testCompilingAFullyVersionedRouteLogsNoWarningForIt(): void
    {
        $dummy = new DummyLogger();
        $app = Waypoint::create();
        $app->configure(function (LoggerOptions $opts) use ($dummy) {
            $opts->add($dummy);
        });
        $app->attach([VersionedWidgetsController::class]);

        $messages = array_column($dummy->logs, 1);
        $this->assertEmpty(array_filter($messages, fn($m) => str_contains($m, '/v1/widgets/list')));
        $this->assertEmpty(array_filter($messages, fn($m) => str_contains($m, '/v2/widgets/override')));
    }

    public function testCompilingWithoutAnyAppNeverThrowsEvenThoughItWouldNormallyLog(): void
    {
        // No Waypoint::create() at all -- a Router built directly the same
        // way FileSystemCacheTest does, proving RouteCompiler's logging
        // stays optional rather than fataling on a null Waypoint instance.
        $this->assertNull(Waypoint::getInstance());

        $router = new Router([CustomersController::class]);

        $this->assertNotEmpty($router->getRoutes());
    }

    public function testCompilingAnInvalidSunsetDateWithoutAnyAppNeverThrowsEitherEvenThoughItWouldNormallyLog(): void
    {
        // Same guard as the unversioned-route warning above, exercised via
        // the *other* logging call site (an invalid #[Sunset] date).
        $this->assertNull(Waypoint::getInstance());

        $router = new Router([InvalidSunsetController::class]);

        $this->assertNull($router->staticRoutes['GET']['/bad-sunset/route']['sunsetHeader']);
    }

    public function testTheWarningIsNotLoggedAgainWhenARebuiltRouterLoadsFromTheCacheInstead(): void
    {
        $tempDir = sys_get_temp_dir() . '/waypoint-test-' . bin2hex(random_bytes(6));
        try {
            $dummy = new DummyLogger();
            $app = Waypoint::create();
            $app->configure(function (LoggerOptions $opts) use ($dummy) {
                $opts->add($dummy);
            });
            $app->configure(function (FileSystemOptions $fs) use ($tempDir) {
                $fs->cacheDirectory = $tempDir;
            });
            $app->attach([CustomersController::class]);
            $firstCompileCount = count($dummy->logs);
            $this->assertGreaterThan(0, $firstCompileCount);

            // A second Router for the exact same controller set: isAvailable()
            // finds the fresh cache still valid, so this loads it instead of
            // recompiling (see Router::__construct()) -- no further logging.
            new Router([CustomersController::class], [], null, null, $app->getContainer()->get(FileSystemOptions::class));

            $this->assertCount($firstCompileCount, $dummy->logs);
        } finally {
            $this->removeDirectory($tempDir);
        }
    }

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
}
