<?php

namespace Waypoint\Tests\Fixtures\Plugins;

use Waypoint\{Container, Environment};
use Waypoint\Options\CorsOptions;
use Waypoint\Plugin\PluginBase;

/** A boot() whose parameters ask for the container, an Options class and a service. */
final class InjectedBootPlugin extends PluginBase
{
    /** @var list<object> */
    public array $got = [];

    public function name(): string
    {
        return 'injected-boot';
    }

    public function boot(Container $container, CorsOptions $cors, Environment $env): void
    {
        $this->got = [$container, $cors, $env];
    }
}
