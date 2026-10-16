<?php

namespace Waypoint\Http;

use Waypoint\Waypoint;
use Waypoint\Options\SecurityHeaderOptions;

/**
 * Fills in the standard security headers (SecurityHeaderOptions) in before(),
 * so they are there on an error a later middleware throws too (after() would
 * be skipped then); a controller or middleware setting one of them afterwards
 * replaces it, and one set before is kept. Register with
 * `$app->use(new SecurityHeadersMiddleware())`.
 */
class SecurityHeadersMiddleware extends MiddlewareBase
{
    protected function before(Request $req, Response $res): bool
    {
        $opts = Waypoint::getConfig(SecurityHeaderOptions::class);

        $headers = [
            'X-Content-Type-Options' => $opts->contentTypeOptionsEnabled ? $opts->contentTypeOptions : null,
            'X-Frame-Options' => $opts->frameOptionsEnabled ? $opts->frameOptions : null,
            'Referrer-Policy' => $opts->referrerPolicyEnabled ? $opts->referrerPolicy : null,
            // HSTS only makes sense (and only works) on a request that itself arrived over HTTPS.
            'Strict-Transport-Security' => $opts->hstsEnabled && $req->https ? $opts->hsts : null,
            'Content-Security-Policy' => $opts->cspEnabled ? $opts->csp : null,
        ];
        foreach ($headers as $name => $value) {
            if ($value !== null && !$res->hasHeader($name)) {
                $res->withHeader($name, $value);
            }
        }
        return true;
    }
}
