<?php

namespace Waypoint\Tests\Fixtures\DTO;

use Waypoint\Attributes\NotBlank;
use Waypoint\FromArray;

class CityDTO
{
    use FromArray;

    #[NotBlank]
    public string $city = '';
}
