<?php

namespace Waypoint\Attributes;

use Attribute;

/** Validation: a non-null property must be a syntactically valid email address. */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Email
{
}
