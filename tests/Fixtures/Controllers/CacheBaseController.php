<?php

namespace Waypoint\Tests\Fixtures\Controllers;

use Waypoint\Attributes\{Authenticated, Middleware};
use Waypoint\Tests\Fixtures\Middlewares\AddHeaderMiddleware;

/** Its attributes reach CacheChildController: a change here must invalidate the compiled cache. */
#[Authenticated]
#[Middleware(AddHeaderMiddleware::class)]
abstract class CacheBaseController
{
    use CacheRoutes;
}
