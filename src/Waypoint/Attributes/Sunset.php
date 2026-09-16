<?php

namespace Waypoint\Attributes;

use Attribute;

/**
 * Schedules a route for removal: $date ('YYYY-MM-DD') becomes the RFC 8594
 * `Sunset` response header, computed once at compile time. On a controller
 * class (every route) or a method (overrides the class). Pair with PHP's
 * native #[\Deprecated] to also send the `Deprecation` header.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Sunset
{
    public function __construct(public string $date)
    {
    }
}
