<?php

namespace Waypoint\Attributes;

use Attribute;

/** Validation: a string property must match $pattern (a full PCRE pattern including delimiters). */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Regex
{
    public function __construct(public string $pattern)
    {
    }
}
