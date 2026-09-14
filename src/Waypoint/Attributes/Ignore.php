<?php

namespace Waypoint\Attributes;

use Attribute;

/**
 *
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Ignore
{    public function __construct()
    {
    }
}
