<?php

namespace Waypoint\Attributes;

use Attribute;

/**
 * Usable on controller classes and on individual route methods; class-level
 * middleware runs before any method-level middleware on that class. When
 * $callable is given as a plain class name, its 'handle' method is used --
 * pass [Class::class, 'method'] to call something else.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Middleware
{
    public array $callable;

    public function __construct(string|array $callable)
    {
        $this->callable = is_array($callable) ? $callable : [$callable, 'handle'];
    }
}
