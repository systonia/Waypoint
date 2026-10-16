<?php

namespace Waypoint\Tests\Fixtures\Controllers;

use Waypoint\Attributes\{Authenticated, Body, Controller, Delete, Get, Permissions, Post, Query, Role};
use Waypoint\Http\{Redirect, View};
use Waypoint\Tests\Fixtures\DTO\{CityDTO, ShipmentDTO};
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

    #[Get('/latest')]
    public function latest(): ShipmentDTO
    {
        return new ShipmentDTO(['to' => ['city' => 'Freiburg']]);
    }

    /** @return list<CityDTO> */
    #[Get('/cities')]
    public function cities(): array
    {
        return [];
    }

    #[Get('/status')]
    public function status(): OrderStatus
    {
        return OrderStatus::Open;
    }

    #[Get('/next-stop')]
    public function nextStop(): ?CityDTO
    {
        return null;
    }

    #[Get('/page')]
    public function page(): View
    {
        return new View('HomePage');
    }

    #[Post('/form')]
    public function form(): View|Redirect
    {
        return new Redirect('/shipments/page');
    }
}
