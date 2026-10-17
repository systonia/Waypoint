<?php

namespace Waypoint\Tests\Fixtures\DTO;

use Waypoint\Attributes\ListOf;
use Waypoint\Attributes\Returns;

/** Array members typed by attributes only, for the editor's element types. */
final class BasketDTO
{
    #[ListOf(CityDTO::class)]
    public array $cities = [];

    #[ListOf('string')]
    public array $tags = [];

    public function __construct(
        #[ListOf(
            type: CityDTO::class,
            map: true
        )]
        public array $byName = [],
    ) {
    }

    #[Returns(
        type: CityDTO::class,
        list: true
    )]
    public function stops(): array
    {
        return $this->cities;
    }

    #[Returns(
        type: CityDTO::class
    )]
    public function first(): CityDTO
    {
        return $this->cities[0];
    }
}
