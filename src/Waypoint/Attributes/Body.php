<?php

namespace Waypoint\Attributes;

use Attribute;

/**
 * Binds the request body to a method parameter: a DTO class (hydrated via its
 * array constructor, see FromArray, then validated), or, for an `array`
 * parameter with `of: OrderLine::class`, a list of them, one per element.
 * `validate: false` hands an invalid DTO to the method instead of answering 422,
 * for an HTML form that shows the errors itself; a View it returns for such a
 * DTO still answers 422.
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
class Body
{
    /**
     * @param string|null $name Unused for bodies; kept for symmetry with #[Param]/#[Query].
     * @param class-string|null $of Element DTO class for an `array`-typed parameter.
     * @param bool $validate False: no 422 before the method runs; it reads `$dto->isValid` and `$dto->errors` itself.
     */
    public function __construct(public ?string $name = null, public ?string $of = null, public bool $validate = true)
    {
    }
}
