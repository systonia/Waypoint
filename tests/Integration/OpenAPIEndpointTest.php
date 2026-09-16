<?php

namespace Waypoint\Tests\Integration;

use Waypoint\Waypoint;
use Waypoint\Options\OpenAPIOptions;
use Waypoint\Tests\Fixtures\Controllers\CustomersController;

final class OpenAPIEndpointTest extends IntegrationTestCase
{
    private function boot(?callable $configure = null): void
    {
        $app = Waypoint::create();
        $app->configure($configure ?? function (OpenAPIOptions $opts) {
            $opts->enabled = true;
        });
        $app->attach([CustomersController::class]);
    }

    public function testEndpointIsOffUntilEnabled(): void
    {
        $this->boot(function (OpenAPIOptions $opts) {
        });

        $output = $this->dispatch('GET', '/openapi/spec.json');

        $this->assertSame(['error' => 'Not found'], json_decode($output, true));
    }

    public function testSwaggerRouteServesTheBundledHtmlFile(): void
    {
        $this->boot();

        $output = $this->dispatch('GET', '/openapi/swagger.html');

        $this->assertStringContainsString('<html', $output);
        $this->assertStringStartsWith('text/html', $this->sentHeaders()['content-type'] ?? '');
    }

    public function testSpecRouteServesTheGeneratedOpenApiDocument(): void
    {
        $this->boot();

        $spec = json_decode($this->dispatch('GET', '/openapi/spec.json'), true);

        $this->assertSame('3.1.0', $spec['openapi']);
        $this->assertArrayHasKey('/customers', $spec['paths']);
        $this->assertArrayNotHasKey('/openapi/spec.json', $spec['paths']);
        $this->assertStringStartsWith('application/json', $this->sentHeaders()['content-type'] ?? '');
    }

    public function testPathIsConfigurable(): void
    {
        $this->boot(function (OpenAPIOptions $opts) {
            $opts->enabled = true;
            $opts->path = 'docs/api/';
        });

        $spec = json_decode($this->dispatch('GET', '/docs/api/spec.json'), true);
        $this->assertSame('3.1.0', $spec['openapi']);

        $output = $this->dispatch('GET', '/openapi/spec.json');
        $this->assertSame(['error' => 'Not found'], json_decode($output, true));
    }

    public function testOnlyGetIsServed(): void
    {
        $this->boot();

        $output = $this->dispatch('POST', '/openapi/spec.json');

        $this->assertSame(['error' => 'Not found'], json_decode($output, true));
    }

    public function testAnUnknownFileUnderThePrefixFallsThroughToRouting(): void
    {
        $this->boot();

        $output = $this->dispatch('GET', '/openapi/other.txt');

        $this->assertSame(['error' => 'Not found'], json_decode($output, true));
    }

}
