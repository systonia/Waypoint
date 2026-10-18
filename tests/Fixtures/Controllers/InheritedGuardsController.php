<?php

namespace Waypoint\Tests\Fixtures\Controllers;

use Waypoint\Attributes\{Controller, Get};

#[Controller('/inherited')]
class InheritedGuardsController extends InheritedGuardsBaseController
{
    #[Get('/secret')]
    public function secret(): array
    {
        return ['secret' => true];
    }
}
