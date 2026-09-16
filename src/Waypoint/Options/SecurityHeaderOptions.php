<?php

namespace Waypoint\Options;

/**
 * The headers Waypoint\Http\SecurityHeadersMiddleware fills in, each with an
 * on/off flag and a value. Four ship enabled with safe defaults; CSP is opt-in
 * because no one-size-fits-all policy exists.
 */
class SecurityHeaderOptions
{
    /** X-Content-Type-Options: no MIME sniffing. */
    public bool $contentTypeOptionsEnabled = true;
    public string $contentTypeOptions = 'nosniff';

    /** X-Frame-Options: clickjacking protection ('SAMEORIGIN' to allow same-origin framing). */
    public bool $frameOptionsEnabled = true;
    public string $frameOptions = 'DENY';

    /** Referrer-Policy. */
    public bool $referrerPolicyEnabled = true;
    public string $referrerPolicy = 'strict-origin-when-cross-origin';

    /** Strict-Transport-Security -- only ever sent on a request that itself arrived over HTTPS. */
    public bool $hstsEnabled = true;
    public string $hsts = 'max-age=31536000; includeSubDomains';

    /** Content-Security-Policy -- sent only when enabled and $csp is set. */
    public bool $cspEnabled = false;
    public ?string $csp = null;
}
