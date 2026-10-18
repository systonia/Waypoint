<?php

namespace Waypoint\Tests\Fixtures\DTO\Validation;

use Waypoint\Attributes\NotBlank;

class UninitializedTypedPropertyDTO
{
    // Typed, no default: PHP leaves this genuinely uninitialized until
    // assigned, unlike untyped/nullable properties which default to null.
    #[NotBlank]
    public string $name;
}
