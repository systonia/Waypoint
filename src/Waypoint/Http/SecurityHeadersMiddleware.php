<?php

namespace Waypoint\Http;

use Waypoint\Waypoint;
use Waypoint\Options\SecurityHeaderOptions;

/**
 * Adds the standard security response headers (see SecurityHeaderOptions
 * for the full list and their defaults) in after(), once the controller
 * has already run -- every header is only *filled in*, via
 * Response::hasHeader(), never overwritten: a controller that already set
 * X-Frame-Options (or any of the others) itself always wins, regardless
 * of where this middleware sits in the pipe.
 *
 * Registered like any other $app->use() middleware -- MiddlewareBase's
 * __invoke() is what makes that possible, see its own doc:
 *
 *   $app->use(new SecurityHeadersMiddleware());
 *
 * ## Position in the $app->use() pipe
 *
 * after() hooks run in *reverse* registration order (App::handleHttp()'s
 * array_reduce nests middlewares onion-style: the *last*-registered
 * middleware's before() runs last, right before the controller, so its
 * after() -- the way back out -- runs *first*). That means, among
 * multiple $app->use() middlewares that each try to fill in the *same*
 * header name, whichever runs its after() *last* wins the
 * Response::hasHeader() check -- i.e. whichever was registered *first*.
 * Register this one first if it should have the final say over other
 * middlewares' own header choices (the usual case for a small, generic
 * security-defaults middleware), or last if something else should be
 * able to override it. Either way, the controller's own explicit header
 * always wins over every middleware, since the controller always runs
 * before any after() hook, on both pipes, regardless of registration
 * order.
 */
class SecurityHeadersMiddleware extends MiddlewareBase
{
    protected function after(Request $req, Response $res): void
    {
        $opts = Waypoint::getConfig(SecurityHeaderOptions::class);

        if ($opts->contentTypeOptionsEnabled) {
            $this->fillIn($res, 'X-Content-Type-Options', $opts->contentTypeOptions);
        }
        if ($opts->frameOptionsEnabled) {
            $this->fillIn($res, 'X-Frame-Options', $opts->frameOptions);
        }
        if ($opts->referrerPolicyEnabled) {
            $this->fillIn($res, 'Referrer-Policy', $opts->referrerPolicy);
        }
        // Never sent on a plain HTTP request, regardless of $hstsEnabled --
        // telling a browser to only ever use HTTPS for this origin makes
        // no sense (and would break local HTTP-only dev setups) for a
        // request that didn't itself arrive over HTTPS in the first place.
        if ($opts->hstsEnabled && self::isHttps()) {
            $this->fillIn($res, 'Strict-Transport-Security', $opts->hsts);
        }
        // Opt-in only -- see SecurityHeaderOptions's own doc on why there's
        // no default CSP value, just a disabled-by-default toggle.
        if ($opts->cspEnabled && $opts->csp !== null) {
            $this->fillIn($res, 'Content-Security-Policy', $opts->csp);
        }
    }

    private function fillIn(Response $res, string $name, string $value): void
    {
        if (!$res->hasHeader($name)) {
            $res->withHeader($name, $value);
        }
    }

    /**
     * True only if this request itself arrived over HTTPS, per the SAPI's
     * own $_SERVER['HTTPS'] (set to a non-empty value other than 'off' --
     * IIS in particular can set it to the literal string 'off' for a
     * plain HTTP request rather than leaving it unset). Deliberately
     * doesn't consult X-Forwarded-Proto or similar reverse-proxy headers:
     * trusting one is a separate, deployment-specific decision (whether
     * this app actually sits behind a proxy that sets it correctly) this
     * class has no way to know, so it isn't made here.
     */
    private static function isHttps(): bool
    {
        $https = $_SERVER['HTTPS'] ?? null;
        return is_string($https) && $https !== '' && strtolower($https) !== 'off';
    }
}
