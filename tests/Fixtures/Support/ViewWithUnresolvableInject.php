<?php

namespace Waypoint\Tests\Fixtures\Support;

use Waypoint\Http\View;
use Waypoint\Attributes\Inject;

/**
 * A View subclass with an #[Inject] property typed to a class that doesn't
 * exist -- exercises Router::injectViewProperties()'s !class_exists($type)
 * skip branch, which a plain View::$env (always resolvable) never reaches.
 */
class ViewWithUnresolvableInject extends View
{
    #[Inject]
    private \Waypoint\Tests\Fixtures\Support\TotallyMadeUpUnresolvableClass $unresolvable;
}
