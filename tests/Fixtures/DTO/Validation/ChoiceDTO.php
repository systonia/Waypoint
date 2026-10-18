<?php

namespace Waypoint\Tests\Fixtures\DTO\Validation;

use Waypoint\Attributes\{OneOf, SameAs};

class ChoiceDTO
{
    #[OneOf(['guest', 'user', 'admin'])]
    public $role;

    #[OneOf([1, 2])]
    public $level;

    public $password;

    #[SameAs('password')]
    public $passwordConfirm;
}
