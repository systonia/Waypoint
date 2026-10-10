<?php

namespace Waypoint\Tests\Fixtures\Controllers;

use Fiber;
use Waypoint\{Csrf, RequestContext, Waypoint};
use Waypoint\Attributes\{Controller, Get, Inject};
use Waypoint\Http\Request;

/** Waits mid-request like an async runtime's I/O would, so another request can run in between. */
#[Controller('/suspending')]
class SuspendingController
{
    #[Inject]
    private Request $request;

    /** @return array<string, mixed> */
    #[Get('/injected')]
    public function injected(): array
    {
        Fiber::suspend();
        return ['name' => $this->request->query('name')];
    }

    /** @return array<string, string|null> */
    #[Get]
    public function index(): array
    {
        Fiber::suspend();
        return ['requestId' => Waypoint::getConfig(RequestContext::class)->getRequestId()];
    }
}
