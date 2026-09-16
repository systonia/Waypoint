<?php

namespace Waypoint\Attributes;

use Attribute;

/** Overrides the OpenAPI component schema name for a DTO class (default: its short class name). */
#[Attribute(Attribute::TARGET_CLASS)]
class Schema
{
    public function __construct(public ?string $name = null)
    {
    }
}
