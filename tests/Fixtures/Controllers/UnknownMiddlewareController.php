<?php

namespace Waypoint\Tests\Fixtures\Controllers;

use Waypoint\Attributes\{Controller, Get, Middleware};

/** Names a middleware class that doesn't exist: compiling it must fail rather than run the route unguarded. */
#[Controller('/unknown-middleware')]
class UnknownMiddlewareController
{
    #[Get('/route')]
    #[Middleware('Totally\\Fake\\MiddlewareClass')]
    public function route(): array
    {
        return ['ok' => true];
    }
}
