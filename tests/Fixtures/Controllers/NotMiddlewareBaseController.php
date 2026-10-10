<?php

namespace Waypoint\Tests\Fixtures\Controllers;

use Waypoint\Attributes\{Controller, Get, Middleware};
use Waypoint\Tests\Fixtures\Middlewares\NotAMiddlewareBase;

/** Names an existing class that doesn't extend MiddlewareBase: compiling it must fail too. */
#[Middleware(NotAMiddlewareBase::class)]
#[Controller('/not-middleware-base')]
class NotMiddlewareBaseController
{
    #[Get('/route')]
    public function route(): array
    {
        return ['ok' => true];
    }
}
