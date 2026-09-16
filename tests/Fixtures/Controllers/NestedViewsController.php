<?php

namespace Waypoint\Tests\Fixtures\Controllers;

use Waypoint\Attributes\{Controller, Get, Query};
use Waypoint\Http\{Request, View};

#[Controller('/nested')]
class NestedViewsController
{
    /** A view living one directory down (Fixtures/Views/Admin/Users.php + Users.css). */
    #[Get('/admin-users')]
    public function adminUsers(Request $req): View
    {
        return new View('Admin/Users', null, partial: $req->acceptPartial);
    }

    /** Builds the view name from request input -- what View's constructor guards against. */
    #[Get('/by-name')]
    public function byName(#[Query] string $name): View
    {
        return new View($name, null, partial: true);
    }
}
