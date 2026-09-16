<?php

namespace Waypoint\Tests\Integration;

use Waypoint\Waypoint;
use Waypoint\OpenAPI\OpenAPIGenerator;
use Waypoint\Options\OpenAPIOptions;
use Waypoint\Tests\Fixtures\Controllers\DocumentedController;

final class OpenAPIGeneratorTest extends IntegrationTestCase
{
    private function generate(): array
    {
        $app = Waypoint::create();
        $app->attach([DocumentedController::class]);
        return (new OpenAPIGenerator($app->getRouter()))->generate();
    }

    public function testInfoDefaultsToOpenApiOptionsDefaultsWhenUnconfigured(): void
    {
        $spec = $this->generate();

        $this->assertSame([
            'title' => 'API Documentation',
            'version' => '1.0.0',
            'description' => 'Generated API documentation',
        ], $spec['info']);
    }

    public function testInfoReflectsConfiguredOpenApiOptions(): void
    {
        $app = Waypoint::create();
        $app->configure(function (OpenAPIOptions $opts) {
            $opts->title = 'Widget API';
            $opts->version = '2.0.0';
        });
        $app->attach([DocumentedController::class]);

        $spec = (new OpenAPIGenerator($app->getRouter()))->generate();

        $this->assertSame('Widget API', $spec['info']['title']);
        $this->assertSame('2.0.0', $spec['info']['version']);
    }

    public function testConfiguredTagsAndServersAppearInTheSpec(): void
    {
        $app = Waypoint::create();
        $app->configure(function (OpenAPIOptions $opts) {
            $opts->servers = [['url' => 'https://api.example.com']];
            $opts->tags = [['name' => 'widgets']];
        });
        $app->attach([DocumentedController::class]);

        $spec = (new OpenAPIGenerator($app->getRouter()))->generate();

        $this->assertSame([['url' => 'https://api.example.com']], $spec['servers']);
        $this->assertSame([['name' => 'widgets']], $spec['tags']);
    }

    public function testConfiguredSecuritySchemesAppearInComponents(): void
    {
        $app = Waypoint::create();
        $app->configure(function (OpenAPIOptions $opts) {
            $opts->securitySchemes = ['bearerAuth' => ['type' => 'http', 'scheme' => 'bearer']];
        });
        $app->attach([DocumentedController::class]);

        $spec = (new OpenAPIGenerator($app->getRouter()))->generate();

        $this->assertSame(
            ['bearerAuth' => ['type' => 'http', 'scheme' => 'bearer']],
            $spec['components']['securitySchemes']
        );
    }

    public function testIgnoredRouteIsExcludedFromPaths(): void
    {
        $spec = $this->generate();
        $this->assertArrayNotHasKey('/documented/hidden', $spec['paths']);
    }

    public function testEveryNonIgnoredRouteIsIncluded(): void
    {
        $spec = $this->generate();

        $this->assertArrayHasKey('/documented/{id}', $spec['paths']);
        $this->assertArrayHasKey('/documented', $spec['paths']);
        $this->assertArrayHasKey('/documented/legacy', $spec['paths']);
    }

    public function testSummaryAndTagsAreReflectedOnTheOperation(): void
    {
        $operation = $this->generate()['paths']['/documented/{id}']['get'];

        $this->assertSame('Fetch a widget', $operation['summary']);
        $this->assertSame(['widgets'], $operation['tags']);
    }

    public function testMethodWithoutASummaryFallsBackToControllerAndMethodName(): void
    {
        $operation = $this->generate()['paths']['/documented']['post'];

        $this->assertSame(DocumentedController::class . '->create', $operation['summary']);
    }

    public function testPathParameterIsDocumentedAsARequiredPathParam(): void
    {
        $operation = $this->generate()['paths']['/documented/{id}']['get'];

        $this->assertSame(
            [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']]],
            $operation['parameters']
        );
    }

    public function testThrowsAttributeAddsAnErrorResponse(): void
    {
        $operation = $this->generate()['paths']['/documented/{id}']['get'];

        $this->assertSame('Widget missing', $operation['responses']['404']['description']);
    }

    public function testThrowsAttributeUsesTheSharedProblemDetailsSchema(): void
    {
        $spec = $this->generate();
        $operation = $spec['paths']['/documented/{id}']['get'];

        $this->assertSame(
            ['$ref' => '#/components/schemas/ProblemDetails'],
            $operation['responses']['404']['content']['application/problem+json']['schema']
        );
        $this->assertArrayHasKey('ProblemDetails', $spec['components']['schemas']);
        $this->assertSame(
            ['type', 'title', 'status'],
            $spec['components']['schemas']['ProblemDetails']['required']
        );
    }

    public function testTheSharedProblemDetailsSchemaIsRegisteredOnlyOnceAcrossMultipleThrowsUsages(): void
    {
        // DocumentedController::show() AND ::replace() both carry
        // #[Throws(NotFoundException::class, ...)] -- this exercises
        // OpenAPIGenerator::ensureProblemDetailsSchema()'s early return for
        // an already-registered schema, not just its registration path.
        $spec = $this->generate();

        $this->assertSame(
            ['$ref' => '#/components/schemas/ProblemDetails'],
            $spec['paths']['/documented/{id}']['put']['responses']['404']['content']['application/problem+json']['schema']
        );
        $this->assertCount(1, array_filter(
            array_keys($spec['components']['schemas']),
            fn(string $name) => $name === 'ProblemDetails'
        ));
    }

    public function testEveryOperationHasADefaultSuccessResponse(): void
    {
        $operation = $this->generate()['paths']['/documented']['post'];

        $this->assertSame('Successful response', $operation['responses']['200']['description']);
    }

    public function testNativeDeprecatedAttributeIsReflected(): void
    {
        $spec = $this->generate();

        $this->assertTrue($spec['paths']['/documented/legacy']['get']['deprecated']);
        $this->assertFalse($spec['paths']['/documented/{id}']['get']['deprecated']);
    }

    public function testOperationIdCombinesControllerAndMethod(): void
    {
        $operation = $this->generate()['paths']['/documented/{id}']['get'];

        $this->assertSame(DocumentedController::class . '_show', $operation['operationId']);
    }
}
