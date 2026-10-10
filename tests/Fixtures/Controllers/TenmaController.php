<?php

namespace Waypoint\Tests\Fixtures\Controllers;

use Waypoint\{RequestContext, Waypoint};
use Waypoint\Attributes\{Controller, Get, Inject};
use Waypoint\Http\Request;

/** Only attached under Tenma (TenmaLiveTest): waits on real async I/O and starts a task of its own. */
#[Controller('/tenma')]
class TenmaController
{
    #[Inject]
    private Request $request;

    /** @return array<string, mixed> */
    #[Get('/interleaved')]
    public function interleaved(): array
    {
        $context = Waypoint::getConfig(RequestContext::class);
        // The child reads the request id later, from a Fiber of its own
        $child = \Tenma::async(function () use ($context) {
            \Tenma::sleep(0.1);
            return $context->getRequestId();
        });
        \Tenma::sleep(0.2);
        return [
            'name' => $this->request->query('name'),
            'requestId' => $context->getRequestId(),
            'childRequestId' => $child->await(),
        ];
    }
}
