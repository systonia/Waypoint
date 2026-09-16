<?php

namespace Waypoint\Attributes;

use Attribute;

/** Routes a controller method for HTTP DELETE. */
#[Attribute(Attribute::TARGET_METHOD)]
class Delete extends HttpRoute
{
    public function getHttpMethod(): string
    {
        return 'DELETE';
    }
}
