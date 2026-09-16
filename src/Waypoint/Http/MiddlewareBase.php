<?php

namespace Waypoint\Http;

/**
 * Base class for every #[Middleware(...)] handler, also usable directly on
 * `$app->use()` via __invoke(). handle() always runs before() -> $next() ->
 * after(); a subclass overrides before()/after(). before() returning false
 * vetoes the request: $next() and after() are skipped (e.g. after sending
 * its own 403), and the response built so far is what gets sent.
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

    final public function __invoke(Request $req, Response $res, callable $next): mixed
    {
        return $this->handle($req, $res, $next);
    }

    /** Return false to stop the chain here. */
    protected function before(Request $req, Response $res): bool
    {
        return true;
    }

    protected function after(Request $req, Response $res): void
    {
    }
}
