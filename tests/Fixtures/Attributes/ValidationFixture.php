<?php

namespace Waypoint\Tests\Fixtures\Attributes;

use Waypoint\Attributes\{NotBlank, Email, Length, Regex, OneOf, SameAs};

class ValidationFixture
{
    #[NotBlank]
    public $blank;

    #[Email]
    public $email;

    #[Length]
    public $defaultLength;

    #[Length(
        min: 3,
        max: 5
    )]
    public $boundedLength;

    #[Regex(
        pattern: '/^\d+$/'
    )]
    public $digitsOnly;

    #[OneOf(['a', 'b'])]
    public $choice;

    #[SameAs('choice')]
    public $confirm;
}
