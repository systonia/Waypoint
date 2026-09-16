<?php

namespace Waypoint\Attributes;

use Attribute;

/** Routes a controller method for HTTP GET. */
#[Attribute(Attribute::TARGET_METHOD)]
class Get extends HttpRoute
{
    public function getHttpMethod(): string
    {
        return 'GET';
    }
}
