<?php

namespace Waypoint\Tests\Fixtures\DTO\Validation;

use Waypoint\Attributes\{NotBlank, Email};

class ContactDTO
{
    #[NotBlank]
    public $name;

    #[Email]
    public $email;
}
