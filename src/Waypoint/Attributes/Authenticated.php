<?php

namespace Waypoint\Attributes;

use Attribute;

/**
 * Requires a valid, decoded JWT (see useJwt()) for this route -- on the
 * class (every route on it) or a single method. Router::dispatch()
 * throws Waypoint\Exceptions\UnauthorizedException when $req->jwt is
 * null; App's default handler for that exception is what decides between
 * a redirect to JWTOptions::$loginRedirectUrl (for a page navigation) and
 * a plain 401 Problem Details response -- see its own doc.
 *
 * #[Role]/#[Permissions] both imply this: checking a role or permission
 * without being authenticated first makes no sense, so either one alone
 * is enough to require a JWT too.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Authenticated
{
}
