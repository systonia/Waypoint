<?php

namespace Waypoint\Tests\Integration;

use Fiber;
use Waypoint\Http\{Request, Response};
use Waypoint\Waypoint;
use Waypoint\Tests\Fixtures\Controllers\SuspendingController;

/** Two requests in Fibers of one process, interleaved where a controller waits: each keeps its own state. */
final class InterleavedRequestsTest extends IntegrationTestCase
{
    public function testEachInterleavedRequestKeepsItsOwnRequestId(): void
    {
        Waypoint::create()->attach([SuspendingController::class]);
        $handle = fn (string $id): Fiber => new Fiber(fn (): Response => Waypoint::create()->handle(Request::fromParts('GET', '/suspending', ['X-Request-Id' => $id])));

        $first = $handle('first');
        $second = $handle('second');
        $first->start();
        $second->start();
        // The second finishes first: the first must not see its id, nor lose its own when the second resets
        $second->resume();
        $first->resume();

        $this->assertSame(['requestId' => 'second'], json_decode($second->getReturn()->getBody(), true));
        $this->assertSame(['requestId' => 'first'], json_decode($first->getReturn()->getBody(), true));
    }
}
