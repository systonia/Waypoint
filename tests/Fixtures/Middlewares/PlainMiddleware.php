<?php

namespace Waypoint\Tests\Fixtures\Middlewares;

use Waypoint\Http\MiddlewareBase;

final class PlainMiddleware extends MiddlewareBase
{
    // No before()/after() overrides: exercises MiddlewareBase's own
    // default pass-through implementations directly.
}
