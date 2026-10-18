<?php

namespace Waypoint\Tests\Fixtures\Middlewares;

use Waypoint\Http\{MiddlewareBase, Request, Response};

final class VetoingMiddleware extends MiddlewareBase
{
    protected function before(Request $req, Response $res): bool
    {
        return false;
    }
}
