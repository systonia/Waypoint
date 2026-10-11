<?php

namespace Waypoint\Tests\Fixtures\Controllers;

use RuntimeException;
use Waypoint\Attributes\{Controller, Get};
use Waypoint\Http\Response;

/** Hands the request over with Response::upgrade(), with a stand-in for the runtime's own response object. */
#[Controller('/upgrade')]
class UpgradeController
{
    #[Get('/ok')]
    public function ok(Response $res): void
    {
        $res->upgrade(new \stdClass());
    }

    #[Get('/then-throws')]
    public function thenThrows(Response $res): void
    {
        $res->upgrade(new \stdClass());
        throw new RuntimeException('failed after the handover');
    }
}
