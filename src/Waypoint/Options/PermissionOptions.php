<?php

namespace Waypoint\Options;

use Waypoint\PermissionProvider;

/** The app's PermissionProvider for #[Permissions(...)]. None configured (default) fails every check closed. */
class PermissionOptions
{
    public ?PermissionProvider $provider = null;
}
