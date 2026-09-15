<?php

namespace Waypoint\Tests\Fixtures\Controllers;

use Waypoint\Attributes\{Controller, Get, Version};

/** 'v10' on purpose -- proves version comparison is numeric (v10 > v2), not lexicographic ('10' < '2' as plain strings). */
#[Controller('/users')]
#[Version('v10')]
class UsersV10Controller
{
    #[Get]
    public function list(): array
    {
        return ['from' => 'v10'];
    }
}
