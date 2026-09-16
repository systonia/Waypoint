<?php

namespace Waypoint\Tests\Integration;

use Waypoint\Waypoint;
use Waypoint\Tests\Fixtures\Controllers\ArgBindingController;
use Waypoint\Tests\Fixtures\Controllers\DocumentedController;

final class ArgumentBindingTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Waypoint::create()->attach([ArgBindingController::class, DocumentedController::class]);
    }

    public function testRequestResponseAndRouterCanBeInjectedAsControllerProperties(): void
    {
        $output = $this->dispatch('GET', '/argbinding/props');

        $this->assertSame(
            ['hasRequest' => true, 'hasResponse' => true, 'hasRouter' => true],
            json_decode($output, true)
        );
    }

    public function testResponseCanBeBoundAsAPlainTypedMethodParameter(): void
    {
        $output = $this->dispatch('GET', '/argbinding/response-param');

        $this->assertSame(['ok' => true], json_decode($output, true));
        $headers = function_exists('xdebug_get_headers') ? xdebug_get_headers() : headers_list();
        $this->assertTrue(
            (bool) array_filter($headers, fn($h) => str_starts_with(strtolower($h), 'x-from-param:'))
        );
    }

    public function testUnattributedScalarParameterBindsByNameFromTheRoutePlaceholder(): void
    {
        $output = $this->dispatch('GET', '/argbinding/scalar/42');

        $this->assertSame(['id' => 42, 'type' => 'integer'], json_decode($output, true));
    }

    public function testUnrecognizedClassParameterAlwaysBindsToNull(): void
    {
        $output = $this->dispatch('GET', '/argbinding/unknown');

        $this->assertSame(['thing' => null], json_decode($output, true));
    }

    public function testArrayBodyWithOfHydratesAndValidatesEachElementIntoTheGivenDto(): void
    {
        $output = $this->dispatch('POST', '/documented/bulk-products', [], [
            ['name' => 'Widget', 'sku' => 'AB12'],
            ['name' => 'Gadget', 'sku' => 'CD34'],
        ]);

        $this->assertSame(['count' => 2, 'skus' => ['AB12', 'CD34']], json_decode($output, true));
    }

    public function testArrayBodyWithOfRejectsAnInvalidElementWithItsIndexInTheErrors(): void
    {
        $output = $this->dispatch('POST', '/documented/bulk-products', [], [
            ['name' => 'Widget', 'sku' => 'AB12'],
            ['name' => '', 'sku' => 'CD34'], // fails #[NotBlank] on name
        ]);

        $decoded = json_decode($output, true);
        $this->assertSame('Validation failed', $decoded['title']);
        $this->assertArrayHasKey('1', $decoded['errors']);
        $this->assertArrayNotHasKey('0', $decoded['errors']);
    }
}
