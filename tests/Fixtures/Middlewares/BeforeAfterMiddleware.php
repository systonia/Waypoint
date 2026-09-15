<?php

namespace Waypoint\Tests\Fixtures\Middlewares;

use Waypoint\Http\{MiddlewareBase, Request, Response};
use Waypoint\Tests\Fixtures\Support\CallTracker;

/**
 * Proves MiddlewareBase's contract end to end: handle() itself is never
 * overridden here (it's `final` -- attempting to would be a compile-time
 * error), only before()/after(), and CallTracker records their order
 * relative to the controller to prove handle() really does wrap $next()
 * between them.
 */
class BeforeAfterMiddleware extends MiddlewareBase
{
    protected function before(Request $req, Response $res): bool
    {
        CallTracker::record('before-after:before');
        $res->withHeader('X-Before', 'yes');
        return true;
    }

    /**
     * Only records the call -- setting a response header here would be a
     * no-op for a plain (non-View) route: Router::buildRouteHandler()'s
     * innermost closure already calls Response::send() right after the
     * controller returns, before the middleware chain unwinds back out
     * through after(). $req/$res are still real, live objects here (e.g.
     * for logging, metrics, cleanup), just past the point where mutating
     * $res has any effect on what was actually sent.
     */
    protected function after(Request $req, Response $res): void
    {
        CallTracker::record('before-after:after');
    }
}
