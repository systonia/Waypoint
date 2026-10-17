<?php

namespace Waypoint\Tests\Fixtures\DTO;

use Waypoint\Attributes\ListOf;

/** A response DTO whose array properties are promoted: #[ListOf] on the constructor parameter applies to the property */
final class ManifestDTO
{
    public function __construct(
        #[ListOf(CityDTO::class)]
        public array $stops = [],
        #[ListOf(
            type: 'string',
            map: true
        )]
        public array $labels = [],
    ) {
    }
}
