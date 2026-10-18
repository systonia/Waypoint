<?php

namespace Waypoint\Tests\Fixtures\DTO\Validation;

use Waypoint\Attributes\{NotBlank, Length};

class MultiRuleDTO
{
    #[NotBlank]
    #[Length(
        min: 3,
        max: 5
    )]
    public $code;
}
