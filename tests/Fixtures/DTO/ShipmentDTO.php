<?php

namespace Waypoint\Tests\Fixtures\DTO;

use Waypoint\FromArray;
use Waypoint\Tests\Fixtures\Enums\{OrderStatus, Priority};

/** Enums, a nested DTO and lists of both, for what FromArray builds beyond scalars */
class ShipmentDTO
{
    use FromArray;

    public OrderStatus $status = OrderStatus::Open;
    public ?Priority $priority = null;
    public CityDTO $to;
    public ?CityDTO $via = null;

    /** @var list<CityDTO> */
    public array $stops = [];

    /** @var Priority[] */
    public array $levels = [];
}
