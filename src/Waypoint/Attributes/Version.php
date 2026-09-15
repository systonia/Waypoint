<?php

namespace Waypoint\Attributes;

use Attribute;

/**
 * URI-prefixes a route with a version segment, e.g. #[Version('v1')] on
 * `#[Get('/users')]` routes it at `/v1/users` instead of `/users`. Usable on
 * a controller class (default for every route on it) and/or a route method
 * (overrides the class's version for that one route) -- RouteCompiler
 * resolves "method wins over class, no attribute at all means no prefix"
 * once per route at compile time, so dispatch never re-reflects to find it.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Version
{
    /**
     * @var string
     */
    public string $value;

    /**
     * @param string $value e.g. 'v1' -- leading/trailing slashes are
     *  stripped, so '/v1/' and 'v1' behave identically.
     */
    public function __construct(string $value)
    {
        $this->value = trim($value, '/');
    }
}
