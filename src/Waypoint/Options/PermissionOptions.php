<?php

namespace Waypoint\Options;

use Waypoint\PermissionProvider;

/** The app's PermissionProvider for #[Permissions(...)]. None configured (default) fails every check closed. */
class PermissionOptions
{
    /** null: a provider a plugin bound with Container::bind(PermissionProvider::class, ...), if any. */
    public ?PermissionProvider $provider = null;
}
