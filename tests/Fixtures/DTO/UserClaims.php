<?php

namespace Waypoint\Tests\Fixtures\DTO;

use Waypoint\FromArray;

/** What the app's JWTs carry */
class UserClaims
{
    use FromArray;

    public int $sub;
    public string $role = 'user';
}
