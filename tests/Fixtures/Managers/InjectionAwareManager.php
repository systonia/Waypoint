<?php

namespace Waypoint\Tests\Fixtures\Managers;

use Waypoint\Attributes\{Manager, Task, Inject};
use Waypoint\Http\Request;
use Waypoint\Router;

/**
 * #[Inject]-ing Request here is nonsensical for a CLI task (there is no
 * HTTP request), which is exactly the point: Router::injectTaskProperties()
 * must skip it rather than try to resolve it, while still handling a
 * self-injected Router normally.
 */
#[Manager('injection-aware')]
class InjectionAwareManager
{
    #[Inject]
    private Request $req;

    #[Inject]
    private Router $selfRouter;

    #[Task('check')]
    public function check(array $argv, int $argc): array
    {
        return [
            'hasRequest' => isset($this->req),
            'hasRouter' => isset($this->selfRouter),
        ];
    }
}
