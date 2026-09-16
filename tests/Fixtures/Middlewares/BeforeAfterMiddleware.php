<?php

namespace Waypoint\Tests\Fixtures\Middlewares;

use Waypoint\Http\{MiddlewareBase, Request, Response};
use Waypoint\Tests\Fixtures\Support\CallTracker;

/**
 * Proves MiddlewareBase's contract end to end: handle() itself is never
 * overridden here (it's `final` -- attempting to would be a compile-time
 * error), only before()/after(), and CallTracker records their order
 * relative to the controller to prove handle() really does wrap $next()
 * between them. Both before() and after() set a response header -- proves
 * both actually reach the client: Response::send() only ever runs once,
 * in App::handleHttp(), after the *entire* middleware chain (this one
 * included) has finished, so a header set from either hook is still
 * there by the time it's sent.
 */
class BeforeAfterMiddleware extends MiddlewareBase
{
    protected function before(Request $req, Response $res): bool
    {
        CallTracker::record('before-after:before');
        $res->withHeader('X-Before', 'yes');
        return true;
    }

    protected function after(Request $req, Response $res): void
    {
        CallTracker::record('before-after:after');
        $res->withHeader('X-After', 'yes');
    }
}
