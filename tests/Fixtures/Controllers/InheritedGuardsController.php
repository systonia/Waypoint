<?php

namespace Waypoint\Tests\Fixtures\Controllers;

use Waypoint\Attributes\{Authenticated, Controller, Get, Middleware};
use Waypoint\Tests\Fixtures\Middlewares\AddHeaderMiddleware;

/** Guards declared two levels up: PHP doesn't inherit attributes, the RouteCompiler must. */
#[Authenticated]
abstract class InheritedGuardsRootController
{
}

#[Middleware(AddHeaderMiddleware::class)]
abstract class InheritedGuardsBaseController extends InheritedGuardsRootController
{
}

#[Controller('/inherited')]
class InheritedGuardsController extends InheritedGuardsBaseController
{
    #[Get('/secret')]
    public function secret(): array
    {
        return ['secret' => true];
    }
}
