<?php

namespace Waypoint\Tests\Fixtures\Controllers;

use Waypoint\Attributes\Authenticated;

/** Guards declared two levels up: PHP doesn't inherit attributes, the RouteCompiler must. */
#[Authenticated]
abstract class InheritedGuardsRootController
{
}
