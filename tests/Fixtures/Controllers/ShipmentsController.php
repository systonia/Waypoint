<?php

namespace Waypoint\Tests\Fixtures\Controllers;

use Waypoint\Attributes\{Authenticated, Body, Controller, Delete, Get, Permissions, Post, Query, Role};
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
    #[Role('dispatcher')]
    public function create(#[Body] ShipmentDTO $shipment): array
    {
        return [];
    }

    #[Get('/{id}')]
    #[Authenticated]
    public function show(int $id): array
    {
        return [];
    }

    #[Delete('/{id}')]
    #[Permissions(['shipments.delete'])]
    public function delete(string $id): array
    {
        return [];
    }

    #[Get('/health')]
    public function health(): array
    {
        return [];
    }
}
