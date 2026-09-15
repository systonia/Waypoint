<?php

namespace Waypoint\Tests\Fixtures\Controllers;

use Waypoint\Attributes\{Controller, Get};
use Waypoint\Http\Response;

#[Controller('/security')]
class SecurityHeaderController
{
    #[Get('/plain')]
    public function plain(): string
    {
        return 'ok';
    }

    // Sets X-Frame-Options itself, before SecurityHeadersMiddleware's
    // after() hook runs -- proves the middleware fills in only what's
    // missing rather than overwriting an explicit controller value.
    #[Get('/custom-frame-options')]
    public function customFrameOptions(Response $res): string
    {
        $res->withHeader('X-Frame-Options', 'SAMEORIGIN');
        return 'ok';
    }
}
