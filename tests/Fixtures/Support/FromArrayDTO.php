<?php

namespace Waypoint\Tests\Fixtures\Support;

use Waypoint\FromArray;
use Waypoint\Attributes\{NotBlank, OneOf};

/** Plain FromArray user, one excluded property -- see FromArrayTest. */
class FromArrayDTO
{
    use FromArray;

    #[NotBlank]
    public string $name = '';
    public int $age = 0;
    #[OneOf(['guest', 'user'])]
    public ?string $role = null;
    public string $excluded = 'default';

    /** @return array<int, string> */
    protected function fromArrayExcludes(): array
    {
        return ['excluded'];
    }
}
