<?php

namespace Waypoint\Tests\Fixtures\Controllers;

use Waypoint\{RequestContext, Waypoint};
use Waypoint\Attributes\{Controller, Get, Inject};
use Waypoint\Http\Request;

/** Only attached under nope (NopeLiveTest): waits on real async I/O and starts a task of its own. */
#[Controller('/nope')]
class NopeController
{
    #[Inject]
    private Request $request;

    /** @return array<string, mixed> */
    #[Get('/interleaved')]
    public function interleaved(): array
    {
        $context = Waypoint::getConfig(RequestContext::class);
        // The child reads the request id later, from a Fiber of its own
        $child = \Nope\async(function () use ($context) {
            \Nope\sleep(0.1);
            return $context->getRequestId();
        });
        \Nope\sleep(0.2);
        return [
            'name' => $this->request->query('name'),
            'requestId' => $context->getRequestId(),
            'childRequestId' => $child->await(),
        ];
    }
}
