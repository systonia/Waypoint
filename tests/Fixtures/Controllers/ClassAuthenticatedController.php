<?php

namespace Waypoint\Tests\Fixtures\Controllers;

use Waypoint\Attributes\{Controller, Get, Authenticated};

#[Controller('/authz-class')]
#[Authenticated]
class ClassAuthenticatedController
{
    #[Get('/plain')]
    public function plain(): array
    {
        return ['ok' => true];
    }
}
