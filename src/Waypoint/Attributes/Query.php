<?php

namespace Waypoint\Attributes;

use Attribute;

/** Binds a query-string value to a method parameter; $name overrides the key when it differs from the parameter name. */
#[Attribute(Attribute::TARGET_PARAMETER)]
class Query
{
    public function __construct(public ?string $name = null)
    {
    }
}
