<?php

namespace Waypoint\Tests\Fixtures\DTO\Shipping;

use Waypoint\FromArray;

class LineItem
{
    use FromArray;

    public int $parcels = 1;
}
