<?php

namespace Waypoint\Attributes;

use Attribute;

/** Routes a controller method for HTTP PATCH. */
#[Attribute(Attribute::TARGET_METHOD)]
class Patch extends HttpRoute
{
    public function getHttpMethod(): string
    {
        return 'PATCH';
    }
}
