<?php

namespace Waypoint\Plugin;

use ReflectionParameter;
use Waypoint\Http\{Request, Response};

/** Binds a plugin's own parameter attribute (or type) to a controller method argument. */
interface ArgumentBinder
{
    /** @return array<string, mixed>|null Compile-time data for this parameter, or null if not this binder's business. */
    public function plan(ReflectionParameter $parameter): ?array;

    /**
     * @param array<string, mixed> $plan What plan() returned.
     * @param array<string, string> $routeParams Route placeholder values.
     */
    public function resolve(array $plan, Request $req, Response $res, array $routeParams): mixed;
}
