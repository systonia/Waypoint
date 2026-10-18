<?php

namespace Waypoint\Tests\Fixtures\Controllers\DuplicateRoutes;

use Waypoint\Attributes\{Controller, Get};

#[Controller('/dup')]
final class DynamicDuplicateController
{
    #[Get('/{id}')]
    public function one(int $id): string { return 'one'; }
}
