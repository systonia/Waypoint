<?php

namespace Waypoint\Tests\Fixtures\Controllers;

use Waypoint\Attributes\{Controller, Get, NoGzip};

/** No class-level #[NoGzip] -- only one specific method opts out, proving the other stays eligible. */
#[Controller('/gzip-method')]
class GzipMethodController
{
    #[Get('/disabled')]
    #[NoGzip]
    public function disabled(): string
    {
        return str_repeat('a', 2000);
    }

    #[Get('/enabled')]
    public function enabled(): string
    {
        return str_repeat('a', 2000);
    }
}
