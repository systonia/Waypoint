<?php

namespace Waypoint\Tests\Fixtures\Services;

use Waypoint\Attributes\Inject;
use Waypoint\Logger;

/**
 * A container-managed service that #[Inject]s Logger -- proves Logger is
 * genuinely usable via #[Inject]/container resolution, not just via `new
 * Logger()` directly.
 */
class ServiceWithInjectedLogger
{
    #[Inject]
    private Logger $logger;

    public function getLogger(): ?Logger
    {
        return isset($this->logger) ? $this->logger : null;
    }
}
