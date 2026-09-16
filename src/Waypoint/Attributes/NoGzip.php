<?php

namespace Waypoint\Attributes;

use Attribute;

/**
 * Opts a controller class, or one specific route method, out of gzip
 * response compression (see Response::send()/CompressionOptions) -- e.g.
 * for a route that already streams pre-compressed bytes, or one where the
 * extra CPU cost per request isn't worth it. Present on either the
 * controller class or the method is enough to disable it for that route;
 * RouteCompiler bakes the combined result into the compiled route plan, so
 * dispatch never has to re-check either at request time.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class NoGzip
{
}
