<?php

namespace Waypoint\Tests\Fixtures\Services;

use Waypoint\Attributes\Inject;
use Waypoint\Environment;

/**
 * A container-managed service that #[Inject]s Environment -- proves
 * Environment is genuinely usable via #[Inject]/container resolution, not
 * just via `new Environment()` directly.
 */
class ServiceWithInjectedEnvironment
{
    #[Inject]
    private Environment $environment;

    public function getEnvironment(): ?Environment
    {
        return isset($this->environment) ? $this->environment : null;
    }
}
