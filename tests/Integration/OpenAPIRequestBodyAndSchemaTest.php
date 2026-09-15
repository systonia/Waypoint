<?php

namespace Waypoint\Tests\Integration;

use Waypoint\Waypoint;
use Waypoint\OpenAPI\OpenAPIGenerator;
use Waypoint\Tests\Fixtures\Controllers\DocumentedController;

/**
 * Covers the parts of OpenAPIGenerator that go beyond path/operation
 * metadata: query/path parameter typing, #[Body] DTO request-body and
 * $ref generation, nested/cyclic model schemas, and the #[Schema]/#[Property]
 * annotations -- none of which worked before (query params were dropped
 * entirely, request bodies were never detected, and a cyclic or
 * union-typed model would either loop forever or fatal).
 */
final class OpenAPIRequestBodyAndSchemaTest extends IntegrationTestCase
{
    private function generate(): array
    {
        $app = Waypoint::create();
        $app->attach([DocumentedController::class]);
        return (new OpenAPIGenerator($app->getRouter()))->generate();
    }

    public function testQueryParametersAreDocumentedWithTheirRealTypes(): void
    {
        $operation = $this->generate()['paths']['/documented/search']['get'];

        $this->assertSame(
            [
                ['name' => 'q', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'string']],
                ['name' => 'limit', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer']],
            ],
            $operation['parameters']
        );
    }

    public function testBodyDtoProducesARequestBodyReferencingItsSchema(): void
    {
        $spec = $this->generate();
        $operation = $spec['paths']['/documented']['post'];

        $this->assertTrue($operation['requestBody']['required']);
        $this->assertSame(
            ['$ref' => '#/components/schemas/CreateProductDTO'],
            $operation['requestBody']['content']['application/json']['schema']
        );
        $this->assertArrayHasKey('CreateProductDTO', $spec['components']['schemas']);
    }

    public function testArrayBodyWithOfProducesAnArraySchemaOfTheElementType(): void
    {
        $spec = $this->generate();
        $operation = $spec['paths']['/documented/bulk-products']['post'];

        $this->assertTrue($operation['requestBody']['required']);
        $this->assertSame(
            ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/CreateProductDTO']],
            $operation['requestBody']['content']['application/json']['schema']
        );
        $this->assertArrayHasKey('CreateProductDTO', $spec['components']['schemas']);
    }

    public function testArrayBodyWithAnUnresolvableOfTypeIsSkippedRatherThanCrashing(): void
    {
        $operation = $this->generate()['paths']['/documented/bulk-ghosts']['post'];

        $this->assertArrayNotHasKey('requestBody', $operation);
    }

    public function testSchemaAttributeOverridesTheGeneratedModelName(): void
    {
        $spec = $this->generate();
        $operation = $spec['paths']['/documented/customers']['post'];

        // CustomerDTO carries #[Schema('Customer')] -- the $ref and the
        // registered component must both use that name, not the class name.
        $this->assertSame(
            ['$ref' => '#/components/schemas/Customer'],
            $operation['requestBody']['content']['application/json']['schema']
        );
        $this->assertArrayHasKey('Customer', $spec['components']['schemas']);
        $this->assertArrayNotHasKey('CustomerDTO', $spec['components']['schemas']);
    }

    public function testNullablePropertiesAreExcludedFromRequired(): void
    {
        $spec = $this->generate();
        $schema = $spec['components']['schemas']['Customer'];

        $this->assertContains('name', $schema['required']);
        $this->assertContains('address', $schema['required']);
        $this->assertNotContains('nickname', $schema['required']);
        $this->assertNotContains('legacyContact', $schema['required']);
    }

    public function testNestedDtoPropertyGeneratesAReferencedComponentSchema(): void
    {
        $spec = $this->generate();
        $customer = $spec['components']['schemas']['Customer'];

        $this->assertSame(['$ref' => '#/components/schemas/AddressDTO'], $customer['properties']['address']);
        $this->assertArrayHasKey('AddressDTO', $spec['components']['schemas']);
        $this->assertSame(['city', 'postalCode'], array_keys($spec['components']['schemas']['AddressDTO']['properties']));
    }

    public function testPropertyAttributeDescriptionExampleFormatAndDeprecatedAreMerged(): void
    {
        $spec = $this->generate();
        $customer = $spec['components']['schemas']['Customer'];

        $this->assertSame('Full name', $customer['properties']['name']['description']);
        $this->assertSame('Ada Lovelace', $customer['properties']['name']['example']);

        $this->assertTrue($customer['properties']['legacyContact']['deprecated']);
        $this->assertSame('email', $customer['properties']['legacyContact']['format']);

        $address = $spec['components']['schemas']['AddressDTO'];
        $this->assertSame('City name', $address['properties']['city']['description']);
        $this->assertSame('Berlin', $address['properties']['city']['example']);
    }

    public function testCyclicModelGraphDoesNotLoopForever(): void
    {
        $spec = $this->generate();

        $this->assertArrayHasKey('CyclicNodeDTO', $spec['components']['schemas']);
        $this->assertSame(
            ['$ref' => '#/components/schemas/CyclicNodeDTO'],
            $spec['components']['schemas']['CyclicNodeDTO']['properties']['parent']
        );
        // The self-reference is nullable, so it must not be required.
        $this->assertNotContains('parent', $spec['components']['schemas']['CyclicNodeDTO']['required']);
    }

    public function testUnionTypedPropertyDoesNotCrashGeneration(): void
    {
        $spec = $this->generate();

        $this->assertArrayHasKey('LegacyDTO', $spec['components']['schemas']);
        // No single OpenAPI type fits a PHP union -- an open/"any" schema
        // is the honest fallback, not a guess at one of the two types.
        $this->assertSame([], $spec['components']['schemas']['LegacyDTO']['properties']['identifier']);
    }

    public function testFloatAndBoolQueryParametersMapToTheirOpenApiTypes(): void
    {
        $operation = $this->generate()['paths']['/documented/metrics']['get'];

        $this->assertSame(['type' => 'number'], $operation['parameters'][0]['schema']);
        $this->assertSame(['type' => 'boolean'], $operation['parameters'][1]['schema']);
    }

    public function testNamedArgumentSummaryAndTagsAreReadTheSameAsPositional(): void
    {
        $operation = $this->generate()['paths']['/documented/{id}']['put'];

        $this->assertSame('Replace a widget', $operation['summary']);
        $this->assertSame(['widgets-named-arg'], $operation['tags']);
    }

    public function testMultipleHttpMethodsOnTheSamePathAreBothPresentAndOrderedByVerb(): void
    {
        $methods = $this->generate()['paths']['/documented/{id}'];

        // get before put, matching the fixed GET/POST/PUT/PATCH/DELETE order
        // buildPaths() sorts by -- this only exercises that sort at all
        // when a path has more than one method.
        $this->assertSame(['get', 'put'], array_keys($methods));
    }

    public function testBodyParameterWithAnUnresolvableTypeIsSkippedRatherThanCrashing(): void
    {
        $operation = $this->generate()['paths']['/documented/raw-body']['post'];

        $this->assertArrayNotHasKey('requestBody', $operation);
    }

    public function testNonPublicPropertyIsExcludedFromTheModelSchema(): void
    {
        $spec = $this->generate();
        $schema = $spec['components']['schemas']['MiscTypesDTO'];

        $this->assertArrayNotHasKey('internalNote', $schema['properties']);
    }

    public function testArrayFloatAndBoolPropertiesMapToTheirOpenApiTypes(): void
    {
        $properties = $this->generate()['components']['schemas']['MiscTypesDTO']['properties'];

        $this->assertSame(['type' => 'array'], $properties['tags']);
        $this->assertSame(['type' => 'number'], $properties['weight']);
        $this->assertSame(['type' => 'boolean'], $properties['active']);
    }

    public function testPropertyTypedAsANonexistentClassFallsBackToAnOpenSchema(): void
    {
        $properties = $this->generate()['components']['schemas']['MiscTypesDTO']['properties'];

        $this->assertSame([], $properties['ghost']);
    }

    public function testMixedTypedPropertyFallsBackToAnOpenSchema(): void
    {
        $properties = $this->generate()['components']['schemas']['MiscTypesDTO']['properties'];

        $this->assertSame([], $properties['anything']);
    }

    public function testUnattributedScalarParameterIsDocumentedAsAQueryParameter(): void
    {
        $params = $this->generate()['paths']['/documented/weird-params']['get']['parameters'];
        $byName = array_column($params, null, 'name');

        $this->assertSame('query', $byName['page']['in']);
        $this->assertSame(['type' => 'integer'], $byName['page']['schema']);
    }

    public function testUntypedQueryParameterFallsBackToAStringSchema(): void
    {
        $params = $this->generate()['paths']['/documented/weird-params']['get']['parameters'];
        $byName = array_column($params, null, 'name');

        $this->assertSame(['type' => 'string'], $byName['untyped']['schema']);
    }

    public function testArrayTypedQueryParameterMapsToAnArraySchema(): void
    {
        $params = $this->generate()['paths']['/documented/weird-params']['get']['parameters'];
        $byName = array_column($params, null, 'name');

        $this->assertSame(['type' => 'array'], $byName['filters']['schema']);
    }

    public function testClassTypedQueryParameterFallsBackToAnOpenSchema(): void
    {
        $params = $this->generate()['paths']['/documented/weird-params']['get']['parameters'];
        $byName = array_column($params, null, 'name');

        $this->assertEquals(new \stdClass(), $byName['notReallyBindable']['schema']);
    }
}
