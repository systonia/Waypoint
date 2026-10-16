<?php

namespace Waypoint\Tests\Fixtures\DTO;

use Waypoint\FromArray;

/** A property without a default: required, since nothing else would set it */
class RequiredNameDTO
{
    use FromArray;

    public string $name;
    public int $qty = 1;
}
