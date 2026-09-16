<?php

namespace Waypoint\Tests\Fixtures\Plugins;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final class Tagged
{
    public function __construct(public string $tag)
    {
    }
}
