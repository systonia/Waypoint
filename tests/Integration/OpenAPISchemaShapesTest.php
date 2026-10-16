<?php

namespace Waypoint\Tests\Integration;

use Waypoint\Waypoint;
use Waypoint\OpenAPI\OpenAPIGenerator;
use Waypoint\Tests\Fixtures\Controllers\ShipmentsController;

final class OpenAPISchemaShapesTest extends IntegrationTestCase
{
    /** @return array<string, mixed> */
    private function generate(): array
    {
        $app = Waypoint::create();
        $app->attach([ShipmentsController::class]);
        return (new OpenAPIGenerator($app->getRouter()))->generate();
    }

    public function testEnumsCarryTheirValues(): void
    {
        $spec = $this->generate();
        $properties = $spec['components']['schemas']['ShipmentDTO']['properties'];

        $this->assertSame(['type' => 'string', 'enum' => ['open', 'done']], $properties['status']);
        $this->assertSame(['type' => ['integer', 'null'], 'enum' => [1, 2, null]], $properties['priority']);
        $this->assertSame(['type' => 'string', 'enum' => ['open', 'done']], $spec['paths']['/shipments']['get']['parameters'][0]['schema']);
    }

    public function testListsAndMapsDescribeTheirElements(): void
    {
        $properties = $this->generate()['components']['schemas']['ShipmentDTO']['properties'];

        $this->assertSame(['type' => 'array', 'items' => ['$ref' => '#/components/schemas/CityDTO']], $properties['stops']);
        $this->assertSame(['type' => 'array', 'items' => ['type' => 'integer', 'enum' => [1, 2]]], $properties['levels']);
        $this->assertSame(['type' => 'object', 'additionalProperties' => ['type' => 'integer']], $properties['weights']);
    }

    public function testNullableRequiredAndReadOnlyFollowWhatHydrationDoes(): void
    {
        $schema = $this->generate()['components']['schemas']['ShipmentDTO'];

        $this->assertSame(['anyOf' => [['$ref' => '#/components/schemas/CityDTO'], ['type' => 'null']]], $schema['properties']['via']);
        $this->assertSame(['$ref' => '#/components/schemas/CityDTO'], $schema['properties']['to']);
        // Only `to` has neither a default nor null
        $this->assertSame(['to'], $schema['required']);
        // private(set): FromArray never fills it from a request
        $this->assertSame(['type' => 'integer', 'readOnly' => true], $schema['properties']['id']);
    }
}
