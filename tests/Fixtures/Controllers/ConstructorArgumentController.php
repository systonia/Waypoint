<?php

namespace Waypoint\Tests\Fixtures\Controllers;

use Waypoint\Attributes\{Controller, Get};

/** A controller whose constructor needs an argument nobody registered: the container cannot build it */
#[Controller('/needs-argument')]
class ConstructorArgumentController
{
    public function __construct(public string $dependency)
    {
    }

    #[Get('')]
    public function index(): array
    {
        return [];
    }
}
