<?php

namespace Waypoint\Attributes;

use Attribute;

/**
 * Attaches a MiddlewareBase subclass to a controller class (runs for every
 * route on it, before any method-level middleware) or one route method:
 * `#[Middleware(RequireAdmin::class)]`. Its handle() is what runs; a class
 * that doesn't extend MiddlewareBase fails the route compile.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Middleware
{
    public function __construct(public string $class)
    {
    }
}
