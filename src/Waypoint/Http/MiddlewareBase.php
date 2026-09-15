<?php

namespace Waypoint\Http;

/**
 * Required base class for every #[Middleware(...)] handler class (see
 * Waypoint\Attributes\Middleware) -- RouteCompiler::collectMiddlewares()
 * rejects (skips, the same as a nonexistent class) any middleware that
 * doesn't extend this. handle() is `final` and always runs
 * before() -> $next() -> after(), in that fixed order; a subclass
 * customizes behavior by overriding before()/after() (both no-ops here)
 * rather than reimplementing the "call $next and return its result"
 * plumbing itself.
 *
 * before() doubles as the veto point: returning false skips $next() (and
 * after()) entirely and short-circuits the chain -- e.g.
 * Fixtures\Middlewares\ShortCircuitMiddleware sends its own 403 response
 * from before() and returns false, so the controller (and every
 * middleware further down the chain) never runs. Returning true (the
 * default) continues as normal.
 *
 * __invoke() (below) is what also makes any subclass directly usable on
 * $app->use()'s own pipe (App::$middlewares, plain callables) -- both
 * pipes call a middleware the exact same way ($mw($req, $res, $next)), so
 * one class works unmodified on either: `$app->use(new
 * SecurityHeadersMiddleware())` for something that should run on every
 * request, or #[Middleware(SomeMiddleware::class)] for one scoped to a
 * single route/controller. Nothing here couples this to routing.
 */
abstract class MiddlewareBase
{
    final public function handle(Request $req, Response $res, callable $next): mixed
    {
        if (!$this->before($req, $res)) {
            return null;
        }
        $result = $next($req, $res);
        $this->after($req, $res);
        return $result;
    }

    /** Delegates to handle() -- see this class's own doc on why, and Waypoint\Http\SecurityHeadersMiddleware for a real $app->use()-registered example. */
    final public function __invoke(Request $req, Response $res, callable $next): mixed
    {
        return $this->handle($req, $res, $next);
    }

    /**
     * Runs before $next() -- override in a subclass for custom
     * pre-processing. Return false to veto the request: $next() (and
     * after()) are skipped entirely, e.g. because this method already
     * sent a response itself (auth/guard-style middleware). Returns true
     * (continue as normal) by default.
     */
    protected function before(Request $req, Response $res): bool
    {
        return true;
    }

    /** Runs after $next() returns -- override in a subclass for custom post-processing. No-op by default. */
    protected function after(Request $req, Response $res): void
    {
    }
}
