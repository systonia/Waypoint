<?php

namespace Waypoint\Attributes;

use Attribute;

/**
 *
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
class Body
{
    /**
     * @param string|null $name Overrides the bound parameter name (unused
     *  for bodies today, kept for symmetry with #[Param]/#[Query]).
     * @param class-string|null $of For a parameter typed `array`: the DTO
     *  class each element of the request body is hydrated (and validated)
     *  into, e.g. `#[Body(of: LineItem::class)] array $items`. Without it,
     *  an `array`-typed #[Body] parameter receives the decoded JSON body
     *  as-is, with no per-element hydration/validation/OpenAPI schema.
     */
    public function __construct(
        public ?string $name = null,
        public ?string $of = null,
    ) {
    }
}
