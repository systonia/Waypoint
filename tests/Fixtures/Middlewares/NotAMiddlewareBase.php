<?php

namespace Waypoint\Tests\Fixtures\Middlewares;

use Waypoint\Http\{Request, Response};
use Waypoint\Tests\Fixtures\Support\CallTracker;

/**
 * Duck-types the old (pre-mandatory-MiddlewareBase) middleware shape --
 * a plain handle(Request, Response, callable): mixed, no base class.
 * RouteCompiler::collectMiddlewares() must reject this now, the same way
 * it already rejects a #[Middleware(...)] naming a nonexistent class.
 */
class NotAMiddlewareBase
{
    public function handle(Request $req, Response $res, callable $next): mixed
    {
        CallTracker::record('not-a-middleware-base');
        return $next($req, $res);
    }
}
