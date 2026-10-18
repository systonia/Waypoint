<?php

namespace Waypoint\Tests\Unit\Attributes;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Waypoint\Attributes\{Controller, Manager, Get, Post, Put, Patch, Delete, Task};
use Waypoint\Tests\Fixtures\Attributes\{RoutingControllerFixture, RoutingDefaultPathFixture, RoutingManagerFixture, RoutingDefaultManagerFixture, RoutingVerbFixture};

/**
 * #[Controller]/#[Manager] name a route/task prefix; #[Get]/#[Post]/... declare
 * the HTTP verb + path a method responds to; #[Task] names a CLI task.
 */
final class RoutingAttributesTest extends TestCase
{
    public function testControllerNormalizesPathWithLeadingSlashAndNoTrailingSlash(): void
    {
        $attr = (new ReflectionClass(RoutingControllerFixture::class))
            ->getAttributes(Controller::class)[0]->newInstance();

        $this->assertSame('/api', $attr->getPath());
    }

    public function testControllerDefaultsToEmptyPath(): void
    {
        $attr = (new ReflectionClass(RoutingDefaultPathFixture::class))
            ->getAttributes(Controller::class)[0]->newInstance();

        $this->assertSame('', $attr->getPath());
    }

    public function testManagerNormalizesNameWithoutAddingSlashes(): void
    {
        $attr = (new ReflectionClass(RoutingManagerFixture::class))
            ->getAttributes(Manager::class)[0]->newInstance();

        $this->assertSame('maintenance', $attr->getName());
    }

    public function testManagerDefaultsToEmptyName(): void
    {
        $attr = (new ReflectionClass(RoutingDefaultManagerFixture::class))
            ->getAttributes(Manager::class)[0]->newInstance();

        $this->assertSame('', $attr->getName());
    }

    #[DataProvider('verbProvider')]
    public function testHttpVerbAttributeReportsMethodAndPath(string $attributeClass, string $method, string $httpMethod, string $expectedPath): void
    {
        $refMethod = (new ReflectionClass(RoutingVerbFixture::class))->getMethod($method);
        $instance = $refMethod->getAttributes($attributeClass)[0]->newInstance();

        $this->assertSame($httpMethod, $instance->getHttpMethod());
        $this->assertSame($expectedPath, $instance->getPath());
    }

    public static function verbProvider(): array
    {
        return [
            'GET' => [Get::class, 'get', 'GET', '/users/{id}'],
            'POST' => [Post::class, 'post', 'POST', '/users'],
            'PUT' => [Put::class, 'put', 'PUT', '/users/{id}'],
            'PATCH' => [Patch::class, 'patch', 'PATCH', '/users/{id}'],
            'DELETE' => [Delete::class, 'delete', 'DELETE', '/users/{id}'],
        ];
    }

    public function testTaskNamePassesThroughUnchanged(): void
    {
        $refMethod = (new ReflectionClass(RoutingVerbFixture::class))->getMethod('cleanup');
        $instance = $refMethod->getAttributes(Task::class)[0]->newInstance();

        $this->assertSame('cleanup', $instance->getName());
    }

    public function testTaskNameTrimsSurroundingSlashes(): void
    {
        $refMethod = (new ReflectionClass(RoutingVerbFixture::class))->getMethod('padded');
        $instance = $refMethod->getAttributes(Task::class)[0]->newInstance();

        $this->assertSame('padded', $instance->getName());
    }

    public function testTaskWithNoNameDefaultsToEmptyString(): void
    {
        $refMethod = (new ReflectionClass(RoutingVerbFixture::class))->getMethod('unnamed');
        $instance = $refMethod->getAttributes(Task::class)[0]->newInstance();

        $this->assertSame('', $instance->getName());
    }
}
