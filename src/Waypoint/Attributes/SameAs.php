<?php

namespace Waypoint\Attributes;

use Attribute;

/** Validation: the property must equal the DTO's $property (e.g. a password confirmation). */
#[Attribute(Attribute::TARGET_PROPERTY)]
class SameAs
{
    public function __construct(public string $property)
    {
    }
}
