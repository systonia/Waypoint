<?php

namespace Waypoint\Attributes;

use Attribute;

/** Validation: the value must match $pattern (a full PCRE pattern including delimiters). Numbers match by their text, null passes, lists, objects and bools never match. */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Regex
{
    public function __construct(public string $pattern)
    {
    }
}
