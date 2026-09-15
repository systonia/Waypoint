<?php

namespace Waypoint\Tests\Fixtures\Middlewares;

use Waypoint\Attributes\Inject;
use Waypoint\Http\{Request, Response};
use Waypoint\Tests\Fixtures\Services\ExampleService;
use Waypoint\Tests\Fixtures\Support\CallTracker;

class InjectingMiddleware
{
    #[Inject]
    private ExampleService $service;

    public function handle(Request $req, Response $res, callable $next): mixed
    {
        CallTracker::record('injecting:' . (isset($this->service) ? get_class($this->service) : 'MISSING'));
        return $next($req, $res);
    }
}
