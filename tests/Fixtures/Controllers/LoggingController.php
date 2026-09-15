<?php

namespace Waypoint\Tests\Fixtures\Controllers;

use Waypoint\Attributes\{Controller, Get};
use Waypoint\Logger;

/**
 * Logs from inside a real dispatched request -- proves Logger::log()
 * picks up the current request's id (RequestContext, set by
 * App::handleHttp()) on its own, without the route handler passing it in.
 */
#[Controller('/logging')]
class LoggingController
{
    #[Get('/ping')]
    public function ping(): string
    {
        (new Logger())->info('handled ping');
        return 'pong';
    }
}
