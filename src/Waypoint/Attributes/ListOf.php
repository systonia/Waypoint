<?php

namespace Waypoint\Attributes;

use Attribute;

/**
 * The element type of an `array` property: a class, an enum or a scalar keyword (int, string, float, bool), as a
 * list, or with $map as values by string key. FromArray builds a DTO or a backed enum case from each element, and
 * OpenAPI documents the array's items (or additionalProperties for a map). On a promoted constructor parameter it
 * applies to the property.
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
class ListOf
{
    public function __construct(
        public string $type,
        public bool $map = false,
    ) {
    }
}
