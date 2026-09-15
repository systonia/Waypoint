<?php

namespace Waypoint\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Waypoint\Container;
use Waypoint\Exceptions\{ContainerException, NotFoundException};
use Waypoint\Tests\Fixtures\Services\{AnotherService, CrashingService, ExampleService};

final class ContainerTest extends TestCase
{
    public function testSetAndGetReturnsSameInstance(): void
    {
        $container = new Container();
        $service = new ExampleService();
        $container->set($service);

        $this->assertSame($service, $container->get(ExampleService::class));
    }

    public function testHasAfterSet(): void
    {
        $container = new Container();
        $container->set(new ExampleService());

        $this->assertTrue($container->has(ExampleService::class));
    }

    public function testHasReturnsTrueForInstantiableClassEvenIfNotRegistered(): void
    {
        $container = new Container();

        $this->assertTrue($container->has(AnotherService::class));
    }

    public function testHasReturnsFalseForNonexistentClass(): void
    {
        $container = new Container();

        $this->assertFalse($container->has('TotallyFakeClass'));
    }

    public function testIsRegisteredIsTrueAfterSet(): void
    {
        $container = new Container();
        $container->set(new ExampleService());

        $this->assertTrue($container->isRegistered(ExampleService::class));
    }

    public function testIsRegisteredIsFalseForAnInstantiableButUnsetClass(): void
    {
        // Unlike has(), isRegistered() must not consider "could be
        // auto-instantiated on demand" the same as "already configured".
        $container = new Container();

        $this->assertFalse($container->isRegistered(AnotherService::class));
    }

    public function testIsRegisteredBecomesTrueAfterGetAutoInstantiates(): void
    {
        $container = new Container();
        $container->get(AnotherService::class);

        $this->assertTrue($container->isRegistered(AnotherService::class));
    }

    public function testGetAutoInstantiatesAndCachesAClassWithNoRequiredConstructorArgs(): void
    {
        $container = new Container();

        $first = $container->get(AnotherService::class);
        $second = $container->get(AnotherService::class);

        $this->assertInstanceOf(AnotherService::class, $first);
        $this->assertSame($first, $second, 'Auto-instantiated services should be cached as singletons');
    }

    public function testGetThrowsNotFoundExceptionForMissingClass(): void
    {
        $container = new Container();

        $this->expectException(NotFoundException::class);
        $container->get('ThisClassDoesNotExist');
    }

    public function testGetThrowsContainerExceptionIfInstantiationCrashes(): void
    {
        $container = new Container();

        $this->expectException(ContainerException::class);
        $container->get(CrashingService::class);
    }

    public function testAllReturnsEveryRegisteredService(): void
    {
        $container = new Container();
        $service = new ExampleService();
        $container->set($service);

        $this->assertSame([ExampleService::class => $service], $container->all());
    }
}
