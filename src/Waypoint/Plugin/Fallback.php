<?php

namespace Waypoint\Plugin;

use Waypoint\Http\{Request, Response};

/**
 * Answers a request no route matched, before the 404: pages from a CMS, short links, legacy URLs. The app's
 * routes always come first. No route attributes, CSRF or auth apply, like for an Endpoint; the response hooks
 * run (with an empty plan), so a hook that rewrites HTML pages sees these too.
 */
interface Fallback
{
    /** True if it answered and wrote the response; false lets the next fallback, and finally the 404, have it. */
    public function serve(string $method, string $path, Request $req, Response $res): bool;
}
