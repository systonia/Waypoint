<?php

namespace Waypoint\Options;

/**
 * The Access-Control-* headers useCors() attaches to every response. Every
 * unset property omits its header entirely -- CORS is off until configured.
 */
class CorsOptions
{
    public ?string $allowOrigin = null;
    public ?string $allowMethods = null;
    public ?string $allowHeaders = null;

    /** Response headers a cross-origin caller may read beyond the browser's default handful. */
    public ?string $exposeHeaders = null;

    /** Seconds a browser may cache a preflight response. */
    public ?int $maxAge = null;

    /** Sends "Access-Control-Allow-Credentials: true"; the header is either exactly that or absent. */
    public bool $allowCredentials = false;

    /** @return array<string, string> Only the headers actually configured. */
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
