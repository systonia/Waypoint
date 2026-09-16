<?php

namespace Waypoint\Attributes;

use Attribute;

/** OpenAPI tags for a controller (every route on it) or one route method; both levels are merged. */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Tags
{
    /** @param string[] $tags */
    public function __construct(public array $tags = [], public ?string $name = null)
    {
    }
}
