<?php

namespace Waypoint\Tests\Fixtures\Controllers;

use Waypoint\Attributes\{Controller, Get, Version};

#[Controller('/users')]
#[Version('v2')]
class UsersV2Controller
{
    #[Get]
    public function list(): array
    {
        return ['from' => 'v2'];
    }
}
