<?php

namespace Waypoint\Tests\Fixtures\DTO\Billing;

use Waypoint\FromArray;

class LineItem
{
    use FromArray;

    public string $sku = '';
}
