<?php

namespace Waypoint\Http;

use InvalidArgumentException;

/**
 * Returned from a route handler to send an HTTP redirect instead of a
 * body -- the third kind of route result next to a plain value
 * (JSON/file/XML via ResultRenderer) and a View:
 *
 *     #[Post('/login')]
 *     public function loginSubmit(Request $req, Response $res): View|Redirect
 *     {
 *         if (!$ok) {
 *             return new View('Login', ['error' => '...']);
 *         }
 *         $res->withCookie('auth_token', $token);
 *         return new Redirect('/dashboard');
 *     }
 *
 * Router::renderResult() turns it into a status + Location header on the
 * Response it was given, nothing more -- any cookie/header the handler
 * already queued on $res (as above) is kept, and the regular middleware
 * after() chain still runs on top of it, exactly as for any other result.
 *
 * A plain value object on purpose, not an exception: a redirect is a
 * perfectly normal outcome of a form POST (the classic
 * POST/redirect/GET pattern), not an error condition, so it belongs on
 * the same return path as the View a validation failure renders
 * instead -- `View|Redirect` on the method signature says exactly what
 * the handler can produce, where a thrown redirect would hide that from
 * the type system, and from every reader, entirely. Failure cases that
 * *should* redirect a browser (no/expired JWT on an #[Authenticated]
 * route) already go through UnauthorizedException +
 * JWTOptions::$loginRedirectUrl instead -- see App's default handlers.
 */
final class Redirect
{
    /**
     * @param string $location Absolute URL or site-relative path, sent
     *  verbatim as the Location header.
     * @param int $status Any 3xx. 302 (Found) by default -- what every
     *  browser has always treated as "go there with GET", regardless of
     *  the original method, which is what a form POST that ends in a
     *  redirect actually wants. Use 303 (See Other) to say that
     *  explicitly, 301/308 for a permanent move, or 307 to preserve the
     *  original method and body.
     */
    public function __construct(
        public readonly string $location,
        public readonly int $status = 302
    ) {
        if ($status < 300 || $status > 399) {
            throw new InvalidArgumentException("Redirect status must be a 3xx code, got {$status}.");
        }
        if ($location === '') {
            throw new InvalidArgumentException('Redirect location must not be empty.');
        }
        // A header value can never legitimately contain a line break --
        // rejected here rather than letting header() silently drop the
        // whole header (or worse, letting a crafted value inject a
        // second one), since $location commonly comes from a request
        // ("?next=..." style) somewhere up the call stack.
        if (str_contains($location, "\r") || str_contains($location, "\n")) {
            throw new InvalidArgumentException('Redirect location must not contain line breaks.');
        }
    }
}
