<?php

namespace Waypoint\Tests\Fixtures\Controllers;

use ArrayObject;
use Waypoint\Attributes\{Controller, Get};

/** Keeps state in an object its constructor builds: a fresh instance per request starts with none */
#[Controller('/visits')]
class VisitsController
{
    private ArrayObject $seen;

    public function __construct()
    {
        $this->seen = new ArrayObject();
    }

    #[Get]
    public function index(): int
    {
        $this->seen->append(true);
        return count($this->seen);
    }
}
