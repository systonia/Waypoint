<?php

namespace Waypoint\Http;

use Waypoint\Waypoint;
use Waypoint\Options\SecurityHeaderOptions;

/**
 * Fills in the standard security headers (SecurityHeaderOptions) in after(),
 * never overwriting one the controller set itself. Register with
 * `$app->use(new SecurityHeadersMiddleware())`. after() hooks run in reverse
 * registration order, so among middlewares filling the same header the one
 * registered first has the final say.
 */
class SecurityHeadersMiddleware extends MiddlewareBase
{
    protected function after(Request $req, Response $res): void
    {
        $opts = Waypoint::getConfig(SecurityHeaderOptions::class);

        $headers = [
            'X-Content-Type-Options' => $opts->contentTypeOptionsEnabled ? $opts->contentTypeOptions : null,
            'X-Frame-Options' => $opts->frameOptionsEnabled ? $opts->frameOptions : null,
            'Referrer-Policy' => $opts->referrerPolicyEnabled ? $opts->referrerPolicy : null,
            // HSTS only makes sense (and only works) on a request that itself arrived over HTTPS.
            'Strict-Transport-Security' => $opts->hstsEnabled && self::isHttps() ? $opts->hsts : null,
            'Content-Security-Policy' => $opts->cspEnabled ? $opts->csp : null,
        ];
        foreach ($headers as $name => $value) {
            if ($value !== null && !$res->hasHeader($name)) {
                $res->withHeader($name, $value);
            }
        }
    }

    /** $_SERVER['HTTPS'] only (IIS sets the literal 'off'); a reverse proxy's X-Forwarded-Proto is deliberately not trusted here. */
    private static function isHttps(): bool
    {
        $https = $_SERVER['HTTPS'] ?? null;
        return is_string($https) && $https !== '' && strtolower($https) !== 'off';
    }
}
