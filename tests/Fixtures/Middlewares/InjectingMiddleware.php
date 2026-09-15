<?php

namespace Waypoint\Tests\Fixtures\Middlewares;

use Waypoint\Attributes\Inject;
use Waypoint\Http\{MiddlewareBase, Request, Response};
use Waypoint\Tests\Fixtures\Services\ExampleService;
use Waypoint\Tests\Fixtures\Support\CallTracker;

class InjectingMiddleware extends MiddlewareBase
{
    #[Inject]
    private ExampleService $service;

    protected function before(Request $req, Response $res): bool
    {
        CallTracker::record('injecting:' . (isset($this->service) ? get_class($this->service) : 'MISSING'));
        return true;
    }
}
