<?php

namespace Waypoint\Attributes;

use Attribute;

/**
 * URI-prefixes a route with a version segment: #[Version('v1')] on
 * #[Get('/users')] routes it at /v1/users. On a controller class (every
 * route) or a method (overrides the class); no attribute means no prefix.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Version
{
    public string $value;

    /** @param string $value e.g. 'v1'; surrounding slashes are stripped. */
    public function __construct(string $value)
    {
        $this->value = trim($value, '/');
    }
}
