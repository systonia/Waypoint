<?php

namespace Waypoint\Attributes;

use Attribute;

/** Excludes a controller (or one route method) from the generated OpenAPI spec. */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Ignore
{
}
