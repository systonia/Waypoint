<?php

namespace Waypoint\Tests\Fixtures\Controllers\DuplicateRoutes;

use Waypoint\Attributes\{Controller, Get};

#[Controller('/dup')]
final class StaticDuplicateController
{
    #[Get('/x')]
    public function one(): string { return 'one'; }

    #[Get('/x')]
    public function two(): string { return 'two'; }
}
