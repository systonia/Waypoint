<?php

namespace Waypoint\Tests\Fixtures\Middlewares;

use Waypoint\Http\{MiddlewareBase, Request, Response};
use Waypoint\Tests\Fixtures\Support\CallTracker;

/**
 * Sends its own response and returns false from before() -- proves
 * MiddlewareBase's veto mechanism prevents $next() (and after(), and the
 * controller) from ever running.
 */
class ShortCircuitMiddleware extends MiddlewareBase
{
    protected function before(Request $req, Response $res): bool
    {
        CallTracker::record('short-circuit');
        $res->status(403)
            ->withHeader('Content-Type', 'application/json')
            ->write(json_encode(['error' => 'blocked by middleware']))
            ->send();
        return false;
    }
}
