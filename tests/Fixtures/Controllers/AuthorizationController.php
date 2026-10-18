<?php

namespace Waypoint\Tests\Fixtures\Controllers;

use Waypoint\Attributes\{Controller, Get, Authenticated, Role, Permissions};

#[Controller('/authz')]
class AuthorizationController
{
    #[Get('/public')]
    public function public(): array
    {
        return ['ok' => true];
    }

    #[Get('/authenticated')]
    #[Authenticated]
    public function authenticated(): array
    {
        return ['ok' => true];
    }

    #[Get('/admin-role')]
    #[Role('admin')]
    public function adminRole(): array
    {
        return ['ok' => true];
    }

    #[Get('/manage-users')]
    #[Permissions(['users.manage'])]
    public function manageUsers(): array
    {
        return ['ok' => true];
    }

    #[Get('/multi-permission')]
    #[Permissions(['users.manage', 'users.delete'])]
    public function multiPermission(): array
    {
        return ['ok' => true];
    }
}
