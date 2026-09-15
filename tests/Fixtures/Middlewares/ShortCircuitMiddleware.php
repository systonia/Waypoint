<?php

namespace Waypoint\Tests\Fixtures\Middlewares;

use Waypoint\Http\{Request, Response};
use Waypoint\Tests\Fixtures\Support\CallTracker;

/**
 * Never calls $next — proves a middleware can veto a request before the
 * controller method runs.
 */
class ShortCircuitMiddleware
{
    public function handle(Request $req, Response $res, callable $next): void
    {
        CallTracker::record('short-circuit');
        $res->status(403)
            ->withHeader('Content-Type', 'application/json')
            ->write(json_encode(['error' => 'blocked by middleware']))
            ->send();
    }
}
