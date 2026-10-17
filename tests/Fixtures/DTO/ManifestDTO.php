<?php

namespace Waypoint\Tests\Fixtures\DTO;

/** A response DTO whose array properties are promoted: their element types are in the constructor's docblock */
final class ManifestDTO
{
    /**
     * @param list<CityDTO> $stops
     * @param array<string, string> $labels
     */
    public function __construct(
        public array $stops = [],
        public array $labels = [],
    ) {
    }
}
