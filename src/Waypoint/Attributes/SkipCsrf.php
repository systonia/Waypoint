<?php

namespace Waypoint\Attributes;

use Attribute;

/**
 * Opts a controller class, or one specific route method, out of CSRF
 * verification (see Waypoint\Csrf/Router::dispatch()) -- e.g. a token-
 * auth-only JSON API with no HTML forms behind it, where a double-submit
 * cookie makes no sense in the first place. Present on either the
 * controller class or the method is enough to skip it for that route;
 * RouteCompiler bakes the combined result into the compiled route plan,
 * the same way #[NoGzip] does, so dispatch never has to re-check either
 * at request time.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class SkipCsrf
{
    public function __construct()
    {
    }
}
