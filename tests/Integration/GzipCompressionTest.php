<?php

namespace Waypoint\Tests\Integration;

use Waypoint\Waypoint;
use Waypoint\Options\CompressionOptions;
use Waypoint\Tests\Fixtures\Controllers\{GzipClassDisabledController, GzipMethodController, CustomersController};

/**
 * Exercises #[NoGzip] end to end -- through real attribute compilation
 * (RouteCompiler) and real dispatch (Router::dispatch()/Response::send()),
 * not by reflecting on the compiled plan directly.
 */
final class GzipCompressionTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Waypoint::create()->attach([
            GzipClassDisabledController::class,
            GzipMethodController::class,
            CustomersController::class,
        ]);
    }

    public function testAnOrdinaryRouteCompressesWhenTheClientAcceptsGzip(): void
    {
        $output = $this->dispatch('GET', '/gzip-method/enabled', ['Accept-Encoding' => 'gzip']);

        $this->assertSame('gzip', $this->sentHeaders()['content-encoding'] ?? null);
        $this->assertStringContainsString('"aaa', gzdecode($output));
    }

    public function testClassLevelNoGzipDisablesCompressionForEveryRouteOnTheController(): void
    {
        $output = $this->dispatch('GET', '/gzip-class-disabled/big', ['Accept-Encoding' => 'gzip']);

        $this->assertArrayNotHasKey('content-encoding', $this->sentHeaders());
        $this->assertStringContainsString('"aaa', $output);
    }

    public function testMethodLevelNoGzipDisablesCompressionOnlyForThatOneRoute(): void
    {
        $disabled = $this->dispatch('GET', '/gzip-method/disabled', ['Accept-Encoding' => 'gzip']);
        $this->assertArrayNotHasKey('content-encoding', $this->sentHeaders());
        $this->assertStringContainsString('"aaa', $disabled);

        $enabled = $this->dispatch('GET', '/gzip-method/enabled', ['Accept-Encoding' => 'gzip']);
        $this->assertSame('gzip', $this->sentHeaders()['content-encoding'] ?? null);
    }

    public function testCompressionOptionsConfiguredViaAppConfigureIsHonored(): void
    {
        Waypoint::reset();
        $app = Waypoint::create();
        $app->configure(function (CompressionOptions $opts) {
            $opts->enabled = false;
        });
        $app->attach([GzipMethodController::class]);

        $output = $this->dispatch('GET', '/gzip-method/enabled', ['Accept-Encoding' => 'gzip']);

        $this->assertArrayNotHasKey('content-encoding', $this->sentHeaders());
        $this->assertStringContainsString('"aaa', $output);
    }

    public function testResponsesBelowTheDefaultThresholdAreNeverCompressed(): void
    {
        // CustomersController's routes return small JSON bodies, well
        // under CompressionOptions::$minBytes's default of 1024 bytes.
        $output = $this->dispatch('GET', '/customers/1/orders', ['Accept-Encoding' => 'gzip']);

        $this->assertArrayNotHasKey('content-encoding', $this->sentHeaders());
        $this->assertSame(['customerId' => '1', 'status' => null], json_decode($output, true));
    }
}
