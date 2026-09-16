<?php

namespace Waypoint\Plugin;

use Waypoint\Http\{Request, Response};

/** Runs after the controller's result was rendered and before the app-level middlewares unwind. */
interface ResponseHook
{
    /** @param array<string, mixed> $plan What this plugin's RouteAttributeCompiler stored for the route ([] if nothing). */
    public function after(array $plan, Request $req, Response $res): void;
}
