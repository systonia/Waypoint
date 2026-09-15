<?php

namespace Waypoint\Tests\Fixtures\Controllers;

use Waypoint\Attributes\{Controller, Get};
use Waypoint\Http\Request;

#[Controller('/whoami')]
class WhoAmIController
{
    #[Get]
    public function show(Request $req): array
    {
        return ['jwt' => $req->jwt];
    }
}
