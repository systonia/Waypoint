<?php

namespace Waypoint\Tests\Fixtures\Services;

use Waypoint\Attributes\Inject;

/**
 * A container-managed service that itself has an #[Inject] property --
 * proves App::initDependencyInjection() wires up a service's own
 * dependencies, not just a controller's or #[Middleware] class's (which
 * Router::injectControllerProperties() already handled per-request).
 */
class ServiceWithInjectedDependency
{
    #[Inject]
    private AnotherService $other;

    public function getOther(): ?AnotherService
    {
        return isset($this->other) ? $this->other : null;
    }
}
