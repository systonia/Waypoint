<?php

namespace Waypoint\Tests\Fixtures\Middlewares;

use Waypoint\Http\{MiddlewareBase, Request, Response};
use Waypoint\Tests\Fixtures\Support\CallTracker;

/** Records before()/after() order without touching response headers, so it can't be confused with SecurityHeadersMiddleware's own effects in SecurityHeadersMiddlewareTest's ordering test. */
final class RecordingMiddleware extends MiddlewareBase
{
    public function __construct(private string $label)
    {
    }

    protected function before(Request $req, Response $res): bool
    {
        CallTracker::record("{$this->label}:before");
        return true;
    }

    protected function after(Request $req, Response $res): void
    {
        CallTracker::record("{$this->label}:after");
    }
}
