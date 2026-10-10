<?php

namespace Waypoint\Tests\Integration;

use Fiber;
use Waypoint\Http\{Request, Response};
use Waypoint\Waypoint;
use Waypoint\Tests\Fixtures\Controllers\{GreetingController, SuspendingController};

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

    public function testAnInjectedRequestBelongsToItsOwnRequestWhenTheyInterleave(): void
    {
        Waypoint::create()->attach([SuspendingController::class]);
        $handle = fn (string $name): Fiber => new Fiber(fn (): Response => Waypoint::create()->handle(Request::fromParts('GET', "/suspending/injected?name=$name")));

        $first = $handle('first');
        $second = $handle('second');
        $first->start();
        $second->start();
        $second->resume();
        $first->resume();

        // One shared controller would answer "second" twice: the second request's Request overwrote the first's
        $this->assertSame(['name' => 'first'], json_decode($first->getReturn()->getBody(), true));
        $this->assertSame(['name' => 'second'], json_decode($second->getReturn()->getBody(), true));
    }

    public function testARegisteredControllerIsClonedPerRequestKeepingItsConstructorSetup(): void
    {
        $app = Waypoint::create();
        $registered = new GreetingController('Moin');
        Waypoint::getContainer()->set($registered);
        $app->attach([GreetingController::class]);

        $this->assertSame('Moin Ada', json_decode($app->handle(Request::fromParts('GET', '/greeting?name=Ada'))->getBody()));
        $this->assertSame('Moin Bo', json_decode($app->handle(Request::fromParts('GET', '/greeting?name=Bo'))->getBody()));
        // The registered instance itself never carries a request
        $this->assertFalse((new \ReflectionProperty($registered, 'request'))->isInitialized($registered));
    }
}
