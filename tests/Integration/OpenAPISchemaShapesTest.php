<?php

namespace Waypoint\Tests\Integration;

use Waypoint\Waypoint;
use Waypoint\OpenAPI\OpenAPIGenerator;
use Waypoint\Options\{LoggerOptions, OpenAPIOptions};
use Waypoint\Tests\Fixtures\Support\DummyLogger;
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

    public function testPromotedPropertiesTakeTheirElementsFromListOfOnTheParameter(): void
    {
        $properties = $this->generate()['components']['schemas']['ManifestDTO']['properties'];

        $this->assertSame(['type' => 'array', 'items' => ['$ref' => '#/components/schemas/CityDTO']], $properties['stops']);
        $this->assertSame(['type' => 'object', 'additionalProperties' => ['type' => 'string']], $properties['labels']);
    }

    public function testIgnoredPropertiesAreLeftOutOfTheSchema(): void
    {
        $builder = new \Waypoint\OpenAPI\SchemaBuilder(new \Waypoint\Logger());
        $profile = $builder->generateModelSchema(\Waypoint\Tests\Fixtures\DTO\ProfileDTO::class);
        $badge = $builder->generateModelSchema(\Waypoint\Tests\Fixtures\DTO\BadgeDTO::class);

        $this->assertSame(['name', 'friend'], array_keys($profile['properties']));
        $this->assertArrayNotHasKey('required', $profile);
        $this->assertSame(['label'], array_keys($badge['properties']));
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

    public function testGuardedRoutesRequireASchemeAndDocument401And403(): void
    {
        $spec = $this->generate();
        $paths = $spec['paths'];

        $this->assertSame([['bearerAuth' => []]], $paths['/shipments']['post']['security']);
        $this->assertSame(['type' => 'http', 'scheme' => 'bearer', 'bearerFormat' => 'JWT'], $spec['components']['securitySchemes']['bearerAuth']);
        $this->assertSame(['200', '401', '403', '422'], array_map('strval', array_keys($paths['/shipments']['post']['responses'])));
        $this->assertSame('Requires the role dispatcher', $paths['/shipments']['post']['responses'][403]['description']);
        $this->assertSame('Requires the permissions shipments.delete', $paths['/shipments/{id}']['delete']['responses'][403]['description']);
        $this->assertSame(['200', '401', '422'], array_map('strval', array_keys($paths['/shipments/{id}']['get']['responses'])));

        // Public: no security, no 401; a plain string placeholder converts nothing, so no 422 either
        $this->assertArrayNotHasKey('security', $paths['/shipments']['get']);
        $this->assertSame(['200', '422'], array_map('strval', array_keys($paths['/shipments']['get']['responses'])));
        $this->assertSame(['200'], array_map('strval', array_keys($paths['/shipments/health']['get']['responses'])));
    }

    public function testWithADocumentWideSecurityPublicRoutesOptOut(): void
    {
        Waypoint::create()->configure(function (OpenAPIOptions $options): void {
            $options->securitySchemes = ['jwt' => ['type' => 'http', 'scheme' => 'bearer']];
            $options->security = [['jwt' => []]];
        });
        $spec = $this->generate();

        $this->assertSame([['jwt' => []]], $spec['security']);
        $this->assertSame([], $spec['paths']['/shipments/health']['get']['security']);
        $this->assertArrayNotHasKey('security', $spec['paths']['/shipments']['post']);
        $this->assertArrayNotHasKey('bearerAuth', $spec['components']['securitySchemes']);
    }

    public function testConfiguredSchemesAreWhatAGuardedRouteRequires(): void
    {
        Waypoint::create()->configure(function (OpenAPIOptions $options): void {
            $options->securitySchemes = ['jwt' => ['type' => 'http', 'scheme' => 'bearer'], 'cookie' => ['type' => 'apiKey', 'in' => 'cookie', 'name' => 'auth']];
        });

        $this->assertSame([['jwt' => []], ['cookie' => []]], $this->generate()['paths']['/shipments']['post']['security']);
    }

    /** @return array<string, mixed> */
    private static function okSchema(array $spec, string $path): array
    {
        return $spec['paths'][$path]['get']['responses'][200]['content']['application/json']['schema'];
    }

    public function testResponsesAreDescribedByTheReturnType(): void
    {
        $spec = $this->generate();

        $this->assertSame(['$ref' => '#/components/schemas/ShipmentDTO'], self::okSchema($spec, '/shipments/latest'));
        $this->assertSame(['type' => 'array', 'items' => ['$ref' => '#/components/schemas/CityDTO']], self::okSchema($spec, '/shipments/cities'));
        $this->assertSame(['type' => 'string', 'enum' => ['open', 'done']], self::okSchema($spec, '/shipments/status'));
        $this->assertSame(['anyOf' => [['$ref' => '#/components/schemas/CityDTO'], ['type' => 'null']]], self::okSchema($spec, '/shipments/next-stop'));
        $this->assertSame(['type' => 'object'], self::okSchema($spec, '/shipments/health'));
    }

    public function testReturnsDescribesListsMapsAndNull(): void
    {
        $spec = $this->generate();

        $this->assertSame(['type' => 'object', 'additionalProperties' => ['type' => 'integer']], self::okSchema($spec, '/shipments/weights'));
        $this->assertSame(['type' => ['array', 'null'], 'items' => ['$ref' => '#/components/schemas/CityDTO']], self::okSchema($spec, '/shipments/maybe-cities'));
        $this->assertSame('Successful response', $spec['paths']['/shipments/cities']['get']['responses'][200]['description']);
    }

    public function testReturnsSetsStatusAndDescription(): void
    {
        $responses = $this->generate()['paths']['/shipments/cities']['post']['responses'];

        $this->assertSame(['201'], array_map('strval', array_keys($responses)));
        $this->assertSame('The city as stored', $responses[201]['description']);
        $this->assertSame(['$ref' => '#/components/schemas/CityDTO'], $responses[201]['content']['application/json']['schema']);
    }

    public function testReturnsWinsOverTheReturnType(): void
    {
        $this->assertSame(['$ref' => '#/components/schemas/CityDTO'], self::okSchema($this->generate(), '/shipments/first-stop'));
    }

    public function testAnUnknownReturnsTypeIsLoggedAndLeftOut(): void
    {
        $logger = new DummyLogger();
        Waypoint::create()->configure(fn (LoggerOptions $options) => $options->add($logger));
        $spec = $this->generate();

        $this->assertSame(['type' => 'object'], self::okSchema($spec, '/shipments/unknown'));
        $messages = array_column($logger->logs, 1);
        $this->assertContains('OpenAPIGenerator: #[Returns] type "NoSuchDTO" does not exist in method ' . ShipmentsController::class . '::unknown.', $messages);
    }

    public function testRoutesAnsweringWithAPageOrARedirectAreLeftOut(): void
    {
        $paths = $this->generate()['paths'];

        $this->assertArrayNotHasKey('/shipments/page', $paths);
        $this->assertArrayNotHasKey('/shipments/form', $paths);
    }
}
