<?php

namespace Waypoint\Http;

use InvalidArgumentException;

/**
 * Returned from a route method to send a redirect instead of a body --
 * `return new Redirect('/dashboard')` next to the View the same handler
 * renders on failure (POST/redirect/GET). Router turns it into status +
 * Location; cookies/headers already queued on the Response are kept.
 * A plain result, not an exception: a redirect is a normal outcome, and
 * `View|Redirect` on the signature says so. Auth failures that should
 * redirect to the login page go through UnauthorizedException + JWTOptions::$loginRedirectUrl.
 */
final class Redirect
{
    /**
     * @param string $location Sent verbatim as the Location header.
     * @param int $status Any 3xx; 302 by default (303 for an explicit See Other, 301/308 permanent, 307 keeps the method).
     */
    public function __construct(public readonly string $location, public readonly int $status = 302)
    {
        if ($status < 300 || $status > 399) {
            throw new InvalidArgumentException("Redirect status must be a 3xx code, got {$status}.");
        }
        if ($location === '') {
            throw new InvalidArgumentException('Redirect location must not be empty.');
        }
        // A line break would let a request-derived location inject a second header.
        if (str_contains($location, "\r") || str_contains($location, "\n")) {
            throw new InvalidArgumentException('Redirect location must not contain line breaks.');
        }
    }
}
