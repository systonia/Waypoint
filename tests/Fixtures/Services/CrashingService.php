<?php

namespace Waypoint\Tests\Fixtures\Services;

class CrashingService
{
    public function __construct()
    {
        throw new \Exception("I always crash!");
    }
}
