<?php

namespace Waypoint\Tests\Fixtures\Support;

use Waypoint\PermissionProvider;

class FakePermissionProvider implements PermissionProvider
{
    /** @var array<int, string> */
    public array $granted = [];

    public function hasPermission(mixed $jwt, string $permission): bool
    {
        return in_array($permission, $this->granted, true);
    }
}
