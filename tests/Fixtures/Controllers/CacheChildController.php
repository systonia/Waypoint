<?php

namespace Waypoint\Tests\Fixtures\Controllers;

use Waypoint\Attributes\{Controller, Get};

#[Controller('/cache-child')]
class CacheChildController extends CacheBaseController
{
    #[Get('/')]
    public function index(): array
    {
        return [];
    }
}
