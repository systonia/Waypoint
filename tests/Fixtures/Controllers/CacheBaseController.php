<?php

namespace Waypoint\Tests\Fixtures\Controllers;

use Waypoint\Attributes\Authenticated;

/** Its attributes reach CacheChildController: a change here must invalidate the compiled cache. */
#[Authenticated]
abstract class CacheBaseController
{
}
