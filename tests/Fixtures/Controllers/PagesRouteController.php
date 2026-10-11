<?php

namespace Waypoint\Tests\Fixtures\Controllers;

use Waypoint\Attributes\{Controller, Get};

/** An app route inside the fallback's area: the route wins. */
#[Controller('/pages')]
class PagesRouteController
{
    /** @return array<string, bool> */
    #[Get('/own')]
    public function own(): array
    {
        return ['route' => true];
    }
}
