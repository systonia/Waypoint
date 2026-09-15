<?php

namespace Waypoint\Tests\Fixtures\Middlewares;

use Waypoint\Http\{Request, Response};
use Waypoint\Tests\Fixtures\Support\CallTracker;

class ClassLevelMiddleware
{
    public function handle(Request $req, Response $res, callable $next): mixed
    {
        CallTracker::record('class-level');
        return $next($req, $res);
    }
}
