<?php

namespace Waypoint\Tests\Fixtures\Controllers;

use Waypoint\Attributes\Layout;

/** The parent of LayoutAttributeController: its #[Layout] reaches every route of the child. */
#[Layout('AltLayout.php')]
abstract class LayoutAttributeBase
{
}
