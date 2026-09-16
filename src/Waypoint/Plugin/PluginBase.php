<?php

namespace Waypoint\Plugin;

use Waypoint\Container;

/** Plugin with every optional part defaulting to "nothing"; override what you need. */
abstract class PluginBase implements Plugin
{
    public function requires(): array
    {
        return [];
    }

    public function classes(): array
    {
        return [];
    }

    public function middlewares(): array
    {
        return [];
    }

    public function hooks(): array
    {
        return [];
    }

    public function cacheInputs(): array
    {
        return [];
    }

    public function boot(Container $container): void
    {
    }
}
