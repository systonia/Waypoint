<?php

namespace Waypoint\Tests\Fixtures\Managers;

use Waypoint\Attributes\{Manager, Task};

#[Manager('cleanup')]
class CleanupManager
{
    #[Task('ping')]
    public function ping(array $argv, int $argc): string
    {
        return 'cleanup-pong';
    }
}
