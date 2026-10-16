<?php

namespace Waypoint\Tests\Fixtures\Controllers;

use Waypoint\Attributes\{Body, Controller, Post};
use Waypoint\Tests\Fixtures\DTO\RequiredNameDTO;

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
}
