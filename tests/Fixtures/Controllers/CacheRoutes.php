<?php

namespace Waypoint\Tests\Fixtures\Controllers;

use Waypoint\Attributes\Get;

/** Routes CacheChildController gets through its base class: a change here must invalidate the compiled cache. */
trait CacheRoutes
{
    #[Get('/from-trait')]
    public function fromTrait(): array
    {
        return [];
    }
}
