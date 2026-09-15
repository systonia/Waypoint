<?php

namespace Waypoint\Options;

/**
 * Configures Waypoint's stateless, double-submit-cookie CSRF protection
 * (see Waypoint\Csrf). Resolved through the container like any other
 * Options class -- configure it via App::configure():
 *
 *   $app->configure(function (CsrfOptions $opts) {
 *       $opts->secret = getenv('CSRF_SECRET');
 *   });
 *
 * Individual controllers/routes can opt out entirely (e.g. a token-auth-only
 * JSON API with no HTML forms) via #[SkipCsrf] -- see Waypoint\Attributes\SkipCsrf.
 */
class CsrfOptions
{
    /**
     * HMAC signing key for issued tokens. Must be set explicitly (e.g. via
     * App::configure) before Csrf::generateToken()/isValidToken() are
     * used. Left uninitialized on purpose, the same as JWTOptions::$secret:
     * PHP throws on first access if it was never assigned, instead of
     * silently signing/accepting tokens against a guessable default.
     *
     * @var string
     */
    public string $secret;

    /**
     * How long an issued token stays valid, in seconds, from the moment
     * it's generated -- part of what gets signed (see Csrf::generateToken()),
     * so it can't be tampered with independently of the signature. Also
     * used as the CSRF cookie's own Max-Age, so the cookie and the token
     * it carries always expire together.
     *
     * @var int
     */
    public int $ttl = 3600;

    /**
     * Name of the double-submit cookie the token is issued under.
     * Deliberately NOT HttpOnly (see Csrf::issueFor()) -- unlike a
     * session/auth cookie, this one must be readable by client-side JS so
     * the AJAX/partial-HTML path can mirror it into $headerName itself.
     *
     * @var string
     */
    public string $cookieName = 'csrf_token';

    /**
     * Request header Csrf::verify() checks first for the submitted token
     * -- the AJAX/partial-HTML path (a plain fetch()/XHR call reading the
     * cookie via document.cookie and setting this header itself).
     *
     * @var string
     */
    public string $headerName = 'X-CSRF-Token';

    /**
     * Request body field Csrf::verify() falls back to when $headerName
     * isn't present -- the classic no-JS <form> path, where the token
     * travels as a plain hidden input (see Csrf::field()) submitted
     * alongside the rest of the form.
     *
     * @var string
     */
    public string $fieldName = '_csrf';

    /**
     * The CSRF cookie's own Secure attribute. Defaults to true (HTTPS
     * only) -- unlike JWTOptions/CorsOptions, which default to the more
     * permissive option, CSRF protection that can be stripped by a plain
     * MITM on HTTP defeats its own purpose; set to false explicitly for a
     * local HTTP-only dev environment.
     *
     * @var bool
     */
    public bool $cookieSecure = true;

    /**
     * The CSRF cookie's own SameSite attribute. 'Lax' (the default) still
     * sends the cookie on a plain top-level navigation (so a link from
     * another site into a GET-rendered form still works), while blocking
     * it on cross-site subrequests -- the right default for a
     * double-submit cookie, which relies on same-origin-only readability
     * for its protection in the first place.
     *
     * @var string
     */
    public string $cookieSameSite = 'Lax';
}
