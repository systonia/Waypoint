<?php

namespace Waypoint\Tests\Integration;

use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use Waypoint\Waypoint;
use Waypoint\Options\RendererOptions;
use Waypoint\Tests\Fixtures\Controllers\{
    CustomersController,
    VersionedWidgetsController,
    DeprecatedRoutesController,
    DeprecatedClassController
};

/**
 * End-to-end: a real dispatched request against a versioned/deprecated
 * route, checking the actual response headers -- as opposed to
 * RouteVersioningTest, which inspects the compiled plan directly. Calling
 * DeprecatedRoutesController::methodLevel() for real is exactly what
 * triggers PHP's own native #[\Deprecated] runtime notice (that's the
 * whole point of the attribute); PHPUnit tracks that separately from pass/
 * fail rather than failing the test over it.
 */
final class RouteVersioningDispatchTest extends IntegrationTestCase
{

    public function testAnUnversionedRouteIsStillReachableAtItsOriginalPath(): void
    {
        $app = Waypoint::create();
        $app->configure(function (RendererOptions $opts) {
            $opts->directory = __DIR__ . '/../Fixtures/Views';
        });
        $app->attach([CustomersController::class]);

        $output = $this->dispatch('GET', '/customers');

        $this->assertStringContainsString('testview hit', $output);
    }

    public function testAVersionedRouteIsReachableOnlyAtItsPrefixedPath(): void
    {
        Waypoint::create()->attach([VersionedWidgetsController::class]);

        $output = $this->dispatch('GET', '/v1/widgets/list');
        $this->assertSame(['from' => 'class-default'], json_decode($output, true));

        $notFound = $this->dispatch('GET', '/widgets/list');
        $this->assertSame(['error' => 'Not found'], json_decode($notFound, true));
    }

    #[IgnoreDeprecations]
    public function testDeprecationAndSunsetHeadersAreSentForADeprecatedRoute(): void
    {
        Waypoint::create()->attach([DeprecatedRoutesController::class]);

        $output = $this->dispatch('GET', '/legacy/method-level');
        $headers = $this->sentHeaders();

        $this->assertSame(['ok' => true], json_decode($output, true));
        $this->assertSame('true', $headers['deprecation'] ?? null);
        $this->assertSame(
            (new \DateTimeImmutable('2099-12-31', new \DateTimeZone('UTC')))->format('D, d M Y H:i:s \G\M\T'),
            $headers['sunset'] ?? null
        );
    }

    public function testNeitherHeaderIsSentForAnOrdinaryRoute(): void
    {
        Waypoint::create()->attach([DeprecatedRoutesController::class]);

        $this->dispatch('GET', '/legacy/not-deprecated');
        $headers = $this->sentHeaders();

        $this->assertArrayNotHasKey('deprecation', $headers);
        $this->assertArrayNotHasKey('sunset', $headers);
    }

    public function testSunsetHeaderAloneIsSentWithoutDeprecationWhenOnlyTheClassSetsSunset(): void
    {
        Waypoint::create()->attach([DeprecatedClassController::class]);

        $this->dispatch('GET', '/legacy2/class-level');
        $headers = $this->sentHeaders();

        $this->assertArrayNotHasKey('deprecation', $headers);
        $this->assertSame(
            (new \DateTimeImmutable('2099-06-15', new \DateTimeZone('UTC')))->format('D, d M Y H:i:s \G\M\T'),
            $headers['sunset'] ?? null
        );
    }
}
