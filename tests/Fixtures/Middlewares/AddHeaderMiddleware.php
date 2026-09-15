<?php

namespace Waypoint\Tests\Fixtures\Middlewares;

use Waypoint\Http\{Request, Response};
use Waypoint\Tests\Fixtures\Support\CallTracker;

class AddHeaderMiddleware
{
    public function handle(Request $req, Response $res, callable $next): mixed
    {
        CallTracker::record('add-header');
        $res->withHeader('X-Traced', 'yes');
        return $next($req, $res);
    }
}
