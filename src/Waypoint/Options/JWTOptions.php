<?php

namespace Waypoint\Options;

/** HS256 JWT signing/verification (Waypoint\JWT, useJwt()). */
class JWTOptions
{
    /** Signing secret. Deliberately uninitialized: reading it unset throws instead of signing with a guessable default. */
    public string $secret;

    public string $alg = 'HS256';

    /** Token lifetime in seconds. */
    public int $ttl = 3600;

    /** Expected prefix on the header value. */
    public string $tokenType = 'Bearer';

    /** Request header the token is read from. */
    public string $header = 'Authorization';

    /** Cookie to also read the token from when the header has none -- what lets a signed-in session survive a full page load. Null disables it. */
    public ?string $cookieName = null;

    /**
     * Where an UnauthorizedException sends a real browser navigation (Sec-Fetch-Mode: navigate) as a
     * 302 instead of 401 JSON. Null (default): always 401 -- right for an API with no login page. A
     * fetch()/XHR call gets the 401 either way.
     */
    public ?string $loginRedirectUrl = null;
}
