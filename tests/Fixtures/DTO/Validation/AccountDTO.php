<?php

namespace Waypoint\Tests\Fixtures\DTO\Validation;

use Waypoint\Attributes\{Length, Regex};

class AccountDTO
{
    #[Length(
        min: 3,
        max: 5
    )]
    public $username;

    #[Regex(
        pattern: '/^\d{4}$/'
    )]
    public $pin;
}
