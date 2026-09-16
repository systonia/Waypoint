<?php

namespace Waypoint\Attributes;

use Attribute;

/**
 * Attaches a MiddlewareBase subclass to a controller class (runs for every
 * route on it, before any method-level middleware) or one route method.
 * Pass the class name; [Class::class, 'handle'] is the only other accepted form.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Middleware
{
    /** @var array{0: string, 1?: string} */
    public array $callable;

    /** @param string|array{0: string, 1?: string} $callable */
    public function __construct(string|array $callable)
    {
        $this->callable = is_array($callable) ? $callable : [$callable, 'handle'];
    }
}
