<?php

namespace Waypoint\Tests\Unit\Routing;

use PHPUnit\Framework\TestCase;
use Waypoint\Container;
use Waypoint\PermissionProvider;
use Waypoint\Routing\PropertyInjector;
use Waypoint\Tests\Fixtures\Services\ServiceWithInjectedInterface;
use Waypoint\Tests\Fixtures\Support\FakePermissionProvider;

final class PropertyInjectorTest extends TestCase
{
    public function testAnInterfaceBoundInTheContainerIsInjectedByReflection(): void
    {
        $container = new Container();
        $provider = new FakePermissionProvider();
        $container->bind(PermissionProvider::class, $provider);

        $this->assertSame($provider, $container->get(ServiceWithInjectedInterface::class)->permissions);
    }

    public function testAnInterfaceBoundInTheContainerIsInjectedFromACompiledPlan(): void
    {
        $container = new Container();
        $provider = new FakePermissionProvider();
        $container->bind(PermissionProvider::class, $provider);
        $target = new ServiceWithInjectedInterface();

        (new PropertyInjector($container))->injectPlanned($target, [['name' => 'permissions', 'type' => PermissionProvider::class]]);

        $this->assertSame($provider, $target->permissions);
    }

    public function testAnUnboundInterfaceStaysUnset(): void
    {
        $target = new ServiceWithInjectedInterface();

        (new PropertyInjector(new Container()))->injectPlanned($target, [['name' => 'permissions', 'type' => PermissionProvider::class]]);
        (new PropertyInjector(new Container()))->injectReflected($target);

        $this->assertFalse(isset($target->permissions));
    }
}
