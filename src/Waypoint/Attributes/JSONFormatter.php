<?php

namespace Waypoint\Attributes;

use Attribute;

/** Sends the route's return value as JSON. This is the default, so this is only ever needed to say so explicitly. */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class JSONFormatter
{
}
