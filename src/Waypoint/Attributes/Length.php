<?php

namespace Waypoint\Attributes;

use Attribute;

/** Validation: a string property's length (mb_strlen) must be within [$min, $max]. */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Length
{
    public function __construct(public int $min = 0, public int $max = PHP_INT_MAX)
    {
    }
}
