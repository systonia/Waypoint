<?php

namespace Waypoint\Attributes;

use Attribute;

/** OpenAPI operation summary for a route method. */
#[Attribute(Attribute::TARGET_METHOD)]
class Summary
{
    public function __construct(public ?string $text = null)
    {
    }
}
