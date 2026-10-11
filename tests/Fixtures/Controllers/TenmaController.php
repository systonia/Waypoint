<?php

namespace Waypoint\Tests\Fixtures\Controllers;

use Waypoint\{RequestContext, Waypoint};
use Waypoint\Attributes\{Controller, Get, Inject};
use Waypoint\Http\{Request, Response};

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

    /** Hands the request over to a Tenma WebSocket that echoes every message, prefixed with the request id it still sees. */
    #[Get('/echo')]
    public function echo(Response $res): void
    {
        $res->upgrade(\Tenma\Http\Response::websocket(function (\Tenma\Http\WebSocket $socket) {
            $context = Waypoint::getConfig(RequestContext::class);
            while (($message = $socket->receive()) !== null) {
                $socket->send($context->getRequestId() . ": $message");
            }
        }));
    }

    /** Hands the request over to a Tenma event stream that sends one event and ends. */
    #[Get('/events')]
    public function events(Response $res): void
    {
        $res->withCookie('seen', '1')->upgrade(\Tenma\Http\Response::events(function (\Tenma\Http\EventStream $events) {
            $events->send('hello');
        }));
    }
}
