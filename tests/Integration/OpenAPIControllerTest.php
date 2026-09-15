<?php

namespace Waypoint\Tests\Integration;

use Waypoint\Waypoint;
use Waypoint\OpenAPI\OpenAPIController;
use Waypoint\Tests\Fixtures\Controllers\CustomersController;

final class OpenAPIControllerTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Waypoint::create()->attach([OpenAPIController::class, CustomersController::class]);
    }

    public function testSwaggerRouteServesTheBundledHtmlFile(): void
    {
        $output = $this->dispatch('GET', '/openapi/swagger.html');
        $this->assertStringContainsString('<html', $output);
    }

    public function testSpecRouteServesTheGeneratedOpenApiDocument(): void
    {
        $output = $this->dispatch('GET', '/openapi/spec.json');
        $spec = json_decode($output, true);

        $this->assertSame('3.1.0', $spec['openapi']);
        $this->assertArrayHasKey('/customers', $spec['paths']);
    }

    public function testOpenApiControllerItselfIsExcludedFromItsOwnSpec(): void
    {
        // #[Ignore] on OpenAPIController keeps its own routes out of the
        // generated document.
        $output = $this->dispatch('GET', '/openapi/spec.json');
        $spec = json_decode($output, true);

        $this->assertArrayNotHasKey('/openapi/spec.json', $spec['paths']);
    }
}
