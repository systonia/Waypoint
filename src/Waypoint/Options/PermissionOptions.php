<?php

namespace Waypoint\Options;

use Waypoint\PermissionProvider;

/**
 * Configures #[Permissions(...)] checking. Resolved through the
 * container like any other Options class -- configure it via
 * App::configure():
 *
 *   $app->configure(function (PermissionOptions $opts) {
 *       $opts->provider = new MyAppPermissionProvider();
 *   });
 *
 * No provider configured (the default) means every #[Permissions(...)]
 * check fails closed -- see Router::dispatch() -- rather than silently
 * allowing access nothing was ever actually granted for.
 */
class PermissionOptions
{
    public ?PermissionProvider $provider = null;
}
