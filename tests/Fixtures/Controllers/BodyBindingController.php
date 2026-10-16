<?php

namespace Waypoint\Tests\Fixtures\Controllers;

use Waypoint\Attributes\{Body, Controller, Post};
use Waypoint\Http\View;
use Waypoint\Tests\Fixtures\DTO\{RequiredNameDTO, ShipmentDTO};

/** #[Body] binding cases: required fields, malformed bodies */
#[Controller('/body')]
class BodyBindingController
{
    #[Post('/required')]
    public function required(#[Body] RequiredNameDTO $dto): array
    {
        return ['name' => $dto->name, 'qty' => $dto->qty];
    }

    /** @param array<array-key, mixed> $data */
    #[Post('/raw')]
    public function raw(#[Body] array $data): array
    {
        return $data;
    }

    #[Post('/shipment')]
    public function shipment(#[Body] ShipmentDTO $dto): array
    {
        return ['status' => $dto->status->value, 'to' => $dto->to->city, 'stops' => count($dto->stops)];
    }

    #[Post('/form')]
    public function form(
        #[Body(
            validate: false
        )]
        RequiredNameDTO $dto
    ): View|array {
        return $dto->isValid ? ['name' => $dto->name] : new View('HomePage', ['errors' => $dto->errors], partial: true);
    }
}
