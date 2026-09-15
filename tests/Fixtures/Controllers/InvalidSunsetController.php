<?php

namespace Waypoint\Tests\Fixtures\Controllers;

use Waypoint\Attributes\{Controller, Get, Sunset};

/** A malformed #[Sunset] date -- must degrade to "no Sunset header" rather than crashing the compile. */
#[Controller('/bad-sunset')]
class InvalidSunsetController
{
    #[Get('/route')]
    #[Sunset(date: 'not-a-date')]
    public function route(): array
    {
        return ['ok' => true];
    }
}
