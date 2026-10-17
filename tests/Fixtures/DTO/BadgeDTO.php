<?php

namespace Waypoint\Tests\Fixtures\DTO;

use Waypoint\Attributes\Ignore;

/** #[Ignore] on a promoted constructor parameter */
final class BadgeDTO
{
    public function __construct(
        #[Ignore]
        public int $id = 0,
        public string $label = '',
    ) {
    }
}
