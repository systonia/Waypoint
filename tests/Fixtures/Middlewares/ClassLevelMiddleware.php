<?php

namespace Waypoint\Tests\Fixtures\Middlewares;

use Waypoint\Http\{MiddlewareBase, Request, Response};
use Waypoint\Tests\Fixtures\Support\CallTracker;

class ClassLevelMiddleware extends MiddlewareBase
{
    protected function before(Request $req, Response $res): bool
    {
        CallTracker::record('class-level');
        return true;
    }
}
