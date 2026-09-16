<?php

namespace Waypoint\Tests\Fixtures\Support;

use Waypoint\FromArray;

/** Plain FromArray user, one excluded property -- see FromArrayTest. */
class FromArrayDTO
{
    use FromArray;

    public string $name = '';
    public int $age = 0;
    public string $excluded = 'default';

    /** @return array<int, string> */
    protected function fromArrayExcludes(): array
    {
        return ['excluded'];
    }
}
