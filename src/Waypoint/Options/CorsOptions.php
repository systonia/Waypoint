<?php

namespace Waypoint\Options;

/**
 * Configures the CORS (Cross-Origin Resource Sharing) headers useCors()
 * attaches to every response. Resolved through the container like any
 * other Options class -- configure it via App::configure():
 *
 *   $app->configure(function (CorsOptions $opts) {
 *       $opts->allowOrigin = 'https://yourdomain.com';
 *       $opts->allowMethods = 'GET, POST, OPTIONS';
 *       $opts->allowHeaders = 'Content-Type, Authorization';
 *       $opts->allowCredentials = true;
 *   });
 *
 *   $app->useCors();
 *
 * Leaving a property unset (the default) omits its header entirely --
 * CORS is off by default, not "wide open by default and locked down by
 * config", the safer failure mode for a security-relevant header set.
 */
class CorsOptions
{
    /**
     * @var string|null
     */
    public ?string $allowOrigin = null;

    /**
     * @var string|null
     */
    public ?string $allowMethods = null;

    /**
     * @var string|null
     */
    public ?string $allowHeaders = null;

    /**
     * Response headers (beyond the small default handful a browser always
     * exposes to JS) a cross-origin caller is allowed to read.
     *
     * @var string|null
     */
    public ?string $exposeHeaders = null;

    /**
     * How long, in seconds, a browser may cache a preflight OPTIONS
     * response before sending another one.
     *
     * @var int|null
     */
    public ?int $maxAge = null;

    /**
     * True to send "Access-Control-Allow-Credentials: true", allowing
     * cookies/Authorization headers on a cross-origin request. There's no
     * meaningful "false" value for this header per the Fetch spec -- it's
     * either present and exactly "true", or simply absent -- so this is a
     * plain bool rather than a nullable string like the others above,
     * which prevents ever sending a blank/"false"-valued header by mistake.
     *
     * @var bool
     */
    public bool $allowCredentials = false;

    /**
     * The "Access-Control-*" response headers actually configured, ready
     * to attach via Response::withHeader() -- only a header whose
     * corresponding property was actually set is included, so an
     * unconfigured CorsOptions produces no headers at all.
     *
     * @return array<string, string>
     */
    public function toHeaders(): array
    {
        return array_filter([
            'Access-Control-Allow-Origin' => $this->allowOrigin,
            'Access-Control-Allow-Methods' => $this->allowMethods,
            'Access-Control-Allow-Headers' => $this->allowHeaders,
            'Access-Control-Expose-Headers' => $this->exposeHeaders,
            'Access-Control-Max-Age' => $this->maxAge !== null ? (string) $this->maxAge : null,
            'Access-Control-Allow-Credentials' => $this->allowCredentials ? 'true' : null,
        ], fn(?string $value): bool => $value !== null);
    }
}
