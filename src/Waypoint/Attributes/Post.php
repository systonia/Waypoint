<?php

namespace Waypoint\Attributes;

use Attribute;

/** Routes a controller method for HTTP POST. */
#[Attribute(Attribute::TARGET_METHOD)]
class Post extends HttpRoute
{
    public function getHttpMethod(): string
    {
        return 'POST';
    }
}
