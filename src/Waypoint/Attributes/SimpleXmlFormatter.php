<?php

namespace Waypoint\Attributes;

use Attribute;

/** Sends the route's return value as a flat `<root>` XML document. */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class SimpleXmlFormatter
{
}
