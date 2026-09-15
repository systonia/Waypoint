<?php

namespace Waypoint\Tests\Unit\Attributes;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Waypoint\Attributes\{Controller, Manager, Get, Post, Put, Patch, Delete, Task};

#[Controller('/api')]
class RoutingAttributes_ControllerFixture
{
}

#[Controller]
class RoutingAttributes_DefaultPathFixture
{
}

#[Manager('/maintenance/')]
class RoutingAttributes_ManagerFixture
{
}

#[Manager]
class RoutingAttributes_DefaultManagerFixture
{
}

class RoutingAttributes_VerbFixture
{
    #[Get('/users/{id}')]
    public function get() {}
    #[Post('/users')]
    public function post() {}
    #[Put('/users/{id}')]
    public function put() {}
    #[Patch('/users/{id}')]
    public function patch() {}
    #[Delete('/users/{id}')]
    public function delete() {}
    #[Task('cleanup')]
    public function cleanup() {}
    #[Task('/padded/')]
    public function padded() {}
    #[Task]
    public function unnamed() {}
}

/**
 * #[Controller]/#[Manager] name a route/task prefix; #[Get]/#[Post]/... declare
 * the HTTP verb + path a method responds to; #[Task] names a CLI task.
 */
final class RoutingAttributesTest extends TestCase
{
    public function testControllerNormalizesPathWithLeadingSlashAndNoTrailingSlash(): void
    {
        $attr = (new ReflectionClass(RoutingAttributes_ControllerFixture::class))
            ->getAttributes(Controller::class)[0]->newInstance();

        $this->assertSame('/api', $attr->getPath());
    }

    public function testControllerDefaultsToEmptyPath(): void
    {
        $attr = (new ReflectionClass(RoutingAttributes_DefaultPathFixture::class))
            ->getAttributes(Controller::class)[0]->newInstance();

        $this->assertSame('', $attr->getPath());
    }

    public function testManagerNormalizesNameWithoutAddingSlashes(): void
    {
        $attr = (new ReflectionClass(RoutingAttributes_ManagerFixture::class))
            ->getAttributes(Manager::class)[0]->newInstance();

        $this->assertSame('maintenance', $attr->getName());
    }

    public function testManagerDefaultsToEmptyName(): void
    {
        $attr = (new ReflectionClass(RoutingAttributes_DefaultManagerFixture::class))
            ->getAttributes(Manager::class)[0]->newInstance();

        $this->assertSame('', $attr->getName());
    }

    #[DataProvider('verbProvider')]
    public function testHttpVerbAttributeReportsMethodAndPath(string $attributeClass, string $method, string $httpMethod, string $expectedPath): void
    {
        $refMethod = (new ReflectionClass(RoutingAttributes_VerbFixture::class))->getMethod($method);
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
        $refMethod = (new ReflectionClass(RoutingAttributes_VerbFixture::class))->getMethod('cleanup');
        $instance = $refMethod->getAttributes(Task::class)[0]->newInstance();

        $this->assertSame('cleanup', $instance->getName());
    }

    public function testTaskNameTrimsSurroundingSlashes(): void
    {
        $refMethod = (new ReflectionClass(RoutingAttributes_VerbFixture::class))->getMethod('padded');
        $instance = $refMethod->getAttributes(Task::class)[0]->newInstance();

        $this->assertSame('padded', $instance->getName());
    }

    public function testTaskWithNoNameDefaultsToEmptyString(): void
    {
        $refMethod = (new ReflectionClass(RoutingAttributes_VerbFixture::class))->getMethod('unnamed');
        $instance = $refMethod->getAttributes(Task::class)[0]->newInstance();

        $this->assertSame('', $instance->getName());
    }
}
