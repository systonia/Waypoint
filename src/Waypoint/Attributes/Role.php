<?php

namespace Waypoint\Attributes;

use Attribute;

/**
 * Requires $req->jwt['role'] to equal $role -- on the class (every route
 * on it) or a single method, method wins if both are present (same
 * "method overrides class, else null" precedence #[Version]/#[Sunset]
 * use -- see RouteCompiler::resolveOverridable()). Implies #[Authenticated]
 * (see its own doc): Router::dispatch() checks $req->jwt !== null first,
 * throwing UnauthorizedException, before ever comparing the role.
 * A role mismatch throws Waypoint\Exceptions\ForbiddenException.
 *
 * Assumes the app's own JWT payload carries a 'role' claim (nothing in
 * Waypoint enforces that shape when issuing tokens -- JWT::encode()
 * accepts any payload) -- for anything more structured than "one role
 * string", see #[Permissions] instead.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Role
{
    public function __construct(
        public string $role,
    ) {
    }
}
