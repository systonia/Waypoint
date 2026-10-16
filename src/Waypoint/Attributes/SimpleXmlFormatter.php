<?php

namespace Waypoint\Attributes;

use Attribute;

/** Sends the route's return value as an XML document under `<root>`: nested arrays and objects become nested elements. */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class SimpleXmlFormatter
{
}
