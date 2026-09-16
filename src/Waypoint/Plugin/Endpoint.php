<?php

namespace Waypoint\Plugin;

use Waypoint\Http\{Request, Response};

/**
 * Answers a request before routing (after public files and view assets), like
 * the OpenAPI endpoint. No route attributes, CSRF or auth apply: use it for
 * things deliberately outside the app's routes; everything else is a controller.
 */
interface Endpoint
{
    /** True if this endpoint handled the request and wrote the response. */
    public function serve(string $method, string $path, Request $req, Response $res): bool;
}
