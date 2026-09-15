<?php

namespace Waypoint\Tests\Fixtures\Managers;

use Waypoint\Attributes\{Manager, Task, Inject, JSONFormatter};
use Waypoint\Tests\Fixtures\Services\ExampleService;

#[Manager('maintenance')]
class MaintenanceManager
{
    #[Inject]
    private ExampleService $service;

    #[Task('ping')]
    #[JSONFormatter]
    public function ping(array $argv, int $argc): string
    {
        return 'pong:' . $argc;
    }

    #[Task('inject-check')]
    public function injectCheck(array $argv, int $argc): string
    {
        return isset($this->service) ? get_class($this->service) : 'MISSING';
    }

    // No #[Task] attribute at all -- Router::compileManager() must skip it,
    // not expose it as a runnable task.
    public function helper(): string
    {
        return 'not a task';
    }

    // #[Task] with no name -- also skipped, since an unnamed task can never
    // be looked up by executeTask().
    #[Task]
    public function unnamedTask(): string
    {
        return 'unreachable';
    }
}
