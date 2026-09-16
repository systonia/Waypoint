<?php

namespace Waypoint\Options;

/**
 * Stateless double-submit-cookie CSRF protection (see Waypoint\Csrf). Off
 * until $secret is set; #[SkipCsrf] opts a single route out.
 */
class CsrfOptions
{
    /** HMAC key. Deliberately uninitialized: reading it unset throws instead of signing against a guessable default. */
    public string $secret;

    /** Token lifetime in seconds (signed into the token, and the cookie's Max-Age). */
    public int $ttl = 3600;

    /** The cookie name. Not HttpOnly on purpose: client-side JS mirrors it into $headerName. */
    public string $cookieName = 'csrf_token';

    /** Request header checked first (the fetch()/XHR path). */
    public string $headerName = 'X-CSRF-Token';

    /** Body field checked second (the plain <form> path, see Csrf::field()). */
    public string $fieldName = '_csrf';

    /** Cookie Secure attribute. True by default -- set false only for HTTP-only local dev. */
    public bool $cookieSecure = true;

    /** Cookie SameSite attribute; 'Lax' still sends it on a top-level navigation into a form. */
    public string $cookieSameSite = 'Lax';
}
