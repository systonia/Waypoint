<?php

namespace Waypoint\Attributes;

use Attribute;

/** Marks a property (or constructor parameter, for discovery) to be resolved from the container -- Request/Response/Router per request, everything else as a shared service. */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
class Inject
{
}
