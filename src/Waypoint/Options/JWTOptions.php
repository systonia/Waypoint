<?php

namespace Waypoint\Options;

/**
 * Undocumented class
 */
class JWTOptions
{
    /**
     * Must be set explicitly (e.g. via App::configure) before JWT::encode()/decode() are used.
     * Left uninitialized on purpose: PHP throws on first access if it was never assigned,
     * instead of silently signing tokens with a guessable default.
     *
     * @var string
     */
    public string $secret;

    /**
     * Undocumented variable
     *
     * @var string
     */
    public string $alg = 'HS256';

    /**
     * Undocumented variable
     *
     * @var integer
     */
    public int $ttl = 3600;

    /**
     * Undocumented variable
     *
     * @var string
     */
    public string $tokenType = 'Bearer';

    /**
     * Undocumented variable
     *
     * @var string
     */
    public string $header = 'Authorization';

    /**
     * Name of a cookie to additionally read the token from, tried by
     * useJwt() whenever the $header above didn't already produce one --
     * a plain browser page navigation never sends a bearer Authorization
     * header, only an explicit fetch()/XHR call that sets one itself, so
     * this is what lets a signed-in session survive a full page load too.
     * Left null (the default) to disable this fallback entirely, since
     * unlike the header it isn't itself validated for a matching Bearer
     * prefix -- reading it at all is opt-in.
     *
     * @var string|null
     */
    public ?string $cookieName = null;

    /**
     * Where App's default #[Authenticated] failure handler redirects a
     * real browser navigation (Sec-Fetch-Mode: navigate -- a hard page
     * load/typed URL/bookmark, where only a real 3xx response works at
     * all, since no client-side JS has run yet to react to anything
     * else) that arrives with no JWT. Left null (the default): every
     * UnauthorizedException always gets a plain 401 Problem Details
     * response instead, regardless of Sec-Fetch-Mode -- the right
     * default for a JSON API with no login *page* to send a browser to
     * in the first place. Has no effect on a request that isn't a real
     * browser navigation (an ordinary fetch()/XHR call, same-origin or
     * cors, still gets 401 JSON either way) -- see App's own doc.
     *
     * @var string|null
     */
    public ?string $loginRedirectUrl = null;
}
