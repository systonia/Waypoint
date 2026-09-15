<?php

namespace Waypoint\Options;

/**
 * Configures Waypoint\Http\SecurityHeadersMiddleware. Resolved through the
 * container like any other Options class -- configure it via
 * App::configure():
 *
 *   $app->configure(function (SecurityHeaderOptions $opts) {
 *       $opts->frameOptions = 'SAMEORIGIN';
 *       $opts->cspEnabled = true;
 *       $opts->csp = "default-src 'self'; script-src 'self' 'unsafe-inline'";
 *   });
 *
 * Every header is independently on/off (the *Enabled flag) and its value
 * independently overridable (the plain-named property) -- nothing here is
 * forced without an opt-out. Four headers ship with a value that's safe as
 * a default for most sites (enabled by default); Content-Security-Policy
 * does not, since a wrong one-size-fits-all CSP would break real pages
 * (inline scripts/styles a given project actually needs) rather than just
 * fail open, so it's opt-in (disabled by default, $csp null until set).
 */
class SecurityHeaderOptions
{
    /** X-Content-Type-Options -- stops a browser from MIME-sniffing a response into executing as something other than its declared Content-Type. */
    public bool $contentTypeOptionsEnabled = true;
    public string $contentTypeOptions = 'nosniff';

    /** X-Frame-Options -- blocks this site from being framed at all by default (clickjacking protection); set to 'SAMEORIGIN' to allow framing by pages on the same origin. */
    public bool $frameOptionsEnabled = true;
    public string $frameOptions = 'DENY';

    /** Referrer-Policy -- strips the full URL from the Referer header sent to other origins, keeping it only for same-origin/downgrade-safe requests. */
    public bool $referrerPolicyEnabled = true;
    public string $referrerPolicy = 'strict-origin-when-cross-origin';

    /**
     * Strict-Transport-Security -- tells the browser to only ever contact
     * this origin over HTTPS for the given duration. SecurityHeadersMiddleware
     * still only ever sends this on a request that itself arrived over
     * HTTPS (see its own doc) regardless of this flag -- enabling it here
     * does not force HTTPS or send the header on a plain HTTP request, so
     * a local HTTP-only dev setup is never affected.
     */
    public bool $hstsEnabled = true;
    public string $hsts = 'max-age=31536000; includeSubDomains';

    /**
     * Content-Security-Policy -- disabled by default (see this class's
     * own doc for why). $csp is only ever sent when both this is true AND
     * $csp itself is a non-null string; set both to actually send one.
     */
    public bool $cspEnabled = false;
    public ?string $csp = null;
}
