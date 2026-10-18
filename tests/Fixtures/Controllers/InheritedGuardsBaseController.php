<?php

namespace Waypoint\Tests\Fixtures\Controllers;

use Waypoint\Attributes\Middleware;
use Waypoint\Tests\Fixtures\Middlewares\AddHeaderMiddleware;

#[Middleware(AddHeaderMiddleware::class)]
abstract class InheritedGuardsBaseController extends InheritedGuardsRootController
{
}
