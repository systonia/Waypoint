<?php

namespace Waypoint\Tests\Fixtures\Controllers;

use Waypoint\Attributes\{Body, Controller, Get, Post, Query};
use Waypoint\Tests\Fixtures\DTO\ShipmentDTO;
use Waypoint\Tests\Fixtures\Enums\OrderStatus;

/** What the OpenAPI document says about enums, nested DTOs, lists and nullable values */
#[Controller('/shipments')]
class ShipmentsController
{
    #[Get('')]
    public function index(#[Query] ?OrderStatus $status = null): array
    {
        return [];
    }

    #[Post('')]
    public function create(#[Body] ShipmentDTO $shipment): array
    {
        return [];
    }
}
