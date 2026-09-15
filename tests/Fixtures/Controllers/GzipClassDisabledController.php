<?php

namespace Waypoint\Tests\Fixtures\Controllers;

use Waypoint\Attributes\{Controller, Get, NoGzip};

/** Class-level #[NoGzip] -- every route on this controller must opt out. */
#[Controller('/gzip-class-disabled')]
#[NoGzip]
class GzipClassDisabledController
{
    #[Get('/big')]
    public function big(): string
    {
        return str_repeat('a', 2000);
    }
}
