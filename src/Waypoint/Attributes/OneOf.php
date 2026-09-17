<?php

namespace Waypoint\Attributes;

use Attribute;

/** Validation: a non-null property value must be one of $values (strict comparison). */
#[Attribute(Attribute::TARGET_PROPERTY)]
class OneOf
{
    /** @param list<scalar> $values */
    public function __construct(public array $values)
    {
    }
}
