<?php

namespace Waypoint\Plugin;

use Waypoint\Http\{Request, Response};

/** Runs before the controller, after the core auth/CSRF checks. Throw an HttpException to stop the request. */
interface Guard
{
    /** @param array<string, mixed> $plan What this plugin's RouteAttributeCompiler stored for the route ([] if nothing). */
    public function check(array $plan, Request $req, Response $res): void;
}
