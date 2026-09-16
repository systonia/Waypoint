<?php

namespace Waypoint\Attributes;

use Attribute;

/**
 * Binds the request body to a method parameter: a DTO class (hydrated via its
 * array constructor, see FromArray, then validated), or -- for an `array`
 * parameter with `of: SomeDto::class` -- a list of them, one per element.
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
class Body
{
    /**
     * @param string|null $name Unused for bodies; kept for symmetry with #[Param]/#[Query].
     * @param class-string|null $of Element DTO class for an `array`-typed parameter.
     */
    public function __construct(public ?string $name = null, public ?string $of = null)
    {
    }
}
