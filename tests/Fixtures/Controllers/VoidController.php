<?php

namespace Waypoint\Tests\Fixtures\Controllers;

use Waypoint\Attributes\{Controller, Delete, Get};
use Waypoint\Http\Response;

/** Actions that build the response themselves and return nothing */
#[Controller('/void')]
class VoidController
{
    #[Get('/text')]
    public function text(Response $res): void
    {
        $res->withHeader('Content-Type', 'text/plain')->write('hello');
    }

    #[Delete('/gone')]
    public function gone(Response $res): void
    {
        $res->status(204);
    }
}
