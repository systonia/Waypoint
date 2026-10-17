<?php

namespace Waypoint\Tests\Fixtures\DTO;

use Waypoint\Attributes\Ignore;
use Waypoint\FromArray;

/** #[Ignore] properties: never in a result, a schema or set from input */
class ProfileDTO
{
    use FromArray;

    public string $name = '';

    #[Ignore]
    public string $passwordHash = '';

    #[Ignore]
    public int $internalId;

    public ?ProfileDTO $friend = null;
}
