<?php

namespace Waypoint\Tests\Fixtures\Services;

use Waypoint\Attributes\Inject;
use Waypoint\PermissionProvider;

class ServiceWithInjectedInterface
{
    #[Inject]
    public PermissionProvider $permissions;
}
