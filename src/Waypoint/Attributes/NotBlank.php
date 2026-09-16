<?php

namespace Waypoint\Attributes;

use Attribute;

/** Validation: the property must not be null, an empty/whitespace-only string, or an empty array. */
#[Attribute(Attribute::TARGET_PROPERTY)]
class NotBlank
{
}
