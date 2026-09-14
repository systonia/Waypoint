<?php

namespace Waypoint\Attributes;

use Attribute;

/**
 *
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Email
{
    public function __construct()
    {
    }
}