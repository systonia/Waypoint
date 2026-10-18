<?php

namespace Waypoint\Tests\Fixtures\Controllers\DuplicateRoutes;

use Waypoint\Attributes\{Controller, Get, Post};

#[Controller('/dup')]
final class DynamicDuplicateAgainController
{
    #[Get('/{id}')]
    public function two(int $id): string { return 'two'; }

    #[Post('/{id}')]
    public function post(int $id): string { return 'post'; }
}
