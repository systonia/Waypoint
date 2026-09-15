<?php

namespace Waypoint\Tests\Unit\Attributes;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Waypoint\Attributes\{Param, Query, Body};

class ParameterBindingAttributes_Fixture
{
    public function handle(
        #[Param] string $customerId,
        #[Param('uuid')] string $userId,
        #[Query] ?string $search,
        #[Query('q')] ?string $term,
        #[Body('payload')] array $body,
        #[Body(of: 'Some\\DTO')] array $items
    ) {
    }
}

/**
 * #[Param]/#[Query]/#[Body] bind a controller-method parameter to a route
 * placeholder, a query-string value, or the request body, respectively.
 * By default they match on the parameter's own name; an explicit name
 * overrides that.
 */
final class ParameterBindingAttributesTest extends TestCase
{
    private function attributeOn(string $param, string $attributeClass): object
    {
        $method = (new ReflectionClass(ParameterBindingAttributes_Fixture::class))->getMethod('handle');
        foreach ($method->getParameters() as $p) {
            if ($p->getName() === $param) {
                return $p->getAttributes($attributeClass)[0]->newInstance();
            }
        }
        self::fail("Parameter $param not found");
    }

    public function testParamDefaultsToMatchingItsOwnParameterName(): void
    {
        $attr = $this->attributeOn('customerId', Param::class);
        $this->assertNull($attr->name);
    }

    public function testParamAcceptsAnOverrideName(): void
    {
        $attr = $this->attributeOn('userId', Param::class);
        $this->assertSame('uuid', $attr->name);
    }

    public function testQueryDefaultsToMatchingItsOwnParameterName(): void
    {
        $attr = $this->attributeOn('search', Query::class);
        $this->assertNull($attr->name);
    }

    public function testQueryAcceptsAnOverrideName(): void
    {
        $attr = $this->attributeOn('term', Query::class);
        $this->assertSame('q', $attr->name);
    }

    public function testBodyAcceptsAnOverrideName(): void
    {
        $attr = $this->attributeOn('body', Body::class);
        $this->assertSame('payload', $attr->name);
    }

    public function testBodyDefaultsOfToNull(): void
    {
        $attr = $this->attributeOn('body', Body::class);
        $this->assertNull($attr->of);
    }

    public function testBodyAcceptsAnElementTypeForArrayCollections(): void
    {
        $attr = $this->attributeOn('items', Body::class);
        $this->assertSame('Some\\DTO', $attr->of);
    }
}
