<?php

namespace Waypoint\Tests\Unit\OpenAPI;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;
use Waypoint\{Waypoint, Router};
use Waypoint\Logger;
use Waypoint\OpenAPI\{OpenAPIGenerator, SchemaBuilder};

/** Branches of OpenAPIGenerator and SchemaBuilder a real app never reaches, exercised directly. */
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

    public function testGenerateModelSchemaSkipsTheComputedValidationProperties(): void
    {
        $schema = (new SchemaBuilder(new Logger()))->generateModelSchema(\Waypoint\Tests\Fixtures\Support\FromArrayDTO::class);

        $this->assertSame(['name', 'age', 'role', 'excluded'], array_keys($schema['properties']));
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
