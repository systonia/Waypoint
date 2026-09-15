<?php

namespace Waypoint\Tests\Fixtures\Controllers;

use Waypoint\Http\View;
use Waypoint\Attributes\{Controller, Get};

/**
 * Dedicated fixture for proving Router::injectViewProperties() wires up
 * #[Inject] properties (View::$env) on a returned View before render().
 */
#[Controller('/view-env')]
class ViewEnvController
{
    #[Get]
    public function show(): View
    {
        return new View('EnvView', partial: true);
    }
}
