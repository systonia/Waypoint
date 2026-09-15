<?php

namespace Waypoint\Tests\Fixtures\Controllers;

use Waypoint\Attributes\{Controller, Get, Version};

/** Same #[Controller]/#[Get] path as UsersV2Controller/UsersV10Controller -- three versions of "the same" GET /users endpoint. */
#[Controller('/users')]
#[Version('v1')]
class UsersV1Controller
{
    #[Get]
    public function list(): array
    {
        return ['from' => 'v1'];
    }
}
