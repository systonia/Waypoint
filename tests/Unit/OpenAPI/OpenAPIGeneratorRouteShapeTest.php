<?php

namespace Waypoint\Tests\Unit\OpenAPI;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;
use Waypoint\{Waypoint, Router};
use Waypoint\Logger;
use Waypoint\OpenAPI\{OpenAPIGenerator, SchemaBuilder};

/**
 * Router::getRoutes() only ever produces one route shape in practice (a
 * plain object with handlerSpec as a 2-element [class, method] array), but
 * OpenAPIGenerator::getHandlerSpec()/buildPaths() defensively tolerate a few
 * other shapes too. These exercise those alternate/defensive branches
 * directly via a stubbed Router, since the real one never produces them.
 */
final class OpenAPIGeneratorRouteShapeTest extends TestCase
{
    protected function setUp(): void
    {
        Waypoint::reset();
        Waypoint::create();
    }

    protected function tearDown(): void
    {
        Waypoint::reset();
    }

    private function generatorWithRoutes(array $routes): OpenAPIGenerator
    {
        $router = $this->createStub(Router::class);
        $router->method('getRoutes')->willReturn($routes);

        return new OpenAPIGenerator($router);
    }

    public function testObjectRouteWithANestedSpecKeyIsAccepted(): void
    {
        $spec = $this->generatorWithRoutes([
            (object) [
                'method' => 'GET',
                'rawPath' => '/nested-spec',
                'handlerSpec' => ['spec' => ['Fake\\Controller', 'fakeMethod']],
            ],
        ])->generate();

        $this->assertArrayHasKey('/nested-spec', $spec['paths']);
        $this->assertSame('Fake\\Controller_fakeMethod', $spec['paths']['/nested-spec']['get']['operationId']);
    }

    public function testArrayRouteWithANestedSpecKeyIsAccepted(): void
    {
        $spec = $this->generatorWithRoutes([
            [
                'method' => 'POST',
                'rawPath' => '/array-shaped',
                'handlerSpec' => ['spec' => ['Fake\\Controller', 'anotherMethod']],
            ],
        ])->generate();

        $this->assertArrayHasKey('/array-shaped', $spec['paths']);
        $this->assertSame('Fake\\Controller_anotherMethod', $spec['paths']['/array-shaped']['post']['operationId']);
    }

    public function testRouteWithAnUnrecognizedHandlerSpecShapeIsSkipped(): void
    {
        $spec = $this->generatorWithRoutes([
            (object) ['method' => 'GET', 'rawPath' => '/unrecognized', 'handlerSpec' => ['not' => 'a spec']],
        ])->generate();

        $this->assertArrayNotHasKey('/unrecognized', $spec['paths']);
    }

    public function testRouteMissingMethodOrPathIsSkipped(): void
    {
        $spec = $this->generatorWithRoutes([
            (object) ['handlerSpec' => ['Fake\\Controller', 'noMethodOrPath']],
        ])->generate();

        $this->assertSame([], $spec['paths']);
    }

    public function testGenerateModelSchemaThrowsForANonexistentClass(): void
    {
        // Every internal call site already checks class_exists() before
        // calling this, so the only way to reach its own guard is directly.
        $this->expectException(RuntimeException::class);
        (new SchemaBuilder(new Logger()))->generateModelSchema('Totally\\Fake\\ClassName');
    }

    public function testCompareVersionsReturnsZeroForTwoEqualVersions(): void
    {
        // The only branch selectEligibleRoutes() itself never reaches: two
        // *different* versions never dedup-tie in practice (each route's
        // own version is unique per group by construction), so this is
        // exercised directly instead.
        $generator = $this->generatorWithRoutes([]);
        $method = new ReflectionMethod($generator, 'compareVersions');

        $this->assertSame(0, $method->invoke($generator, 'v1', 'v1'));
    }

    public function testCompareVersionsOrdersNumericallyNotLexicographically(): void
    {
        $generator = $this->generatorWithRoutes([]);
        $method = new ReflectionMethod($generator, 'compareVersions');

        $this->assertGreaterThan(0, $method->invoke($generator, 'v10', 'v2'));
        $this->assertLessThan(0, $method->invoke($generator, 'v2', 'v10'));
    }
}
