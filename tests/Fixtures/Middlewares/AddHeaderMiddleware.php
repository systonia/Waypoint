<?php

namespace Waypoint\Tests\Fixtures\Middlewares;

use Waypoint\Http\{MiddlewareBase, Request, Response};
use Waypoint\Tests\Fixtures\Support\CallTracker;

class AddHeaderMiddleware extends MiddlewareBase
{
    protected function before(Request $req, Response $res): bool
    {
        CallTracker::record('add-header');
        $res->withHeader('X-Traced', 'yes');
        return true;
    }
}
