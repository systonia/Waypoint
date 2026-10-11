<?php

namespace Waypoint\Tests\Integration;

use RuntimeException;
use Waypoint\Http\{Request, Response};
use Waypoint\Tests\Fixtures\Controllers\UpgradeController;
use Waypoint\Waypoint;

/** Response::upgrade() reaches the runtime only when no exception handler wrote the response instead. */
final class UpgradeTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $app = Waypoint::create();
        $app->use(function (Request $req, Response $res, callable $next): mixed {
            $result = $next($req, $res);
            if ($req->query('fail') !== null) {
                throw new RuntimeException('after() of an app middleware failed');
            }
            return $result;
        });
        $app->attach([UpgradeController::class]);
    }

    public function testTheHandedOverObjectComesBackFromHandle(): void
    {
        $res = Waypoint::create()->handle(Request::fromParts('GET', '/upgrade/ok'));

        $this->assertInstanceOf(\stdClass::class, $res->getUpgrade());
    }

    public function testAnExceptionInTheControllerAfterTheHandoverDropsIt(): void
    {
        $res = Waypoint::create()->handle(Request::fromParts('GET', '/upgrade/then-throws'));

        $this->assertNull($res->getUpgrade());
        $this->assertSame(500, $res->getStatus());
    }

    public function testAnExceptionInAnAppMiddlewareAfterTheHandoverDropsIt(): void
    {
        $res = Waypoint::create()->handle(Request::fromParts('GET', '/upgrade/ok?fail=1'));

        $this->assertNull($res->getUpgrade());
        $this->assertSame(500, $res->getStatus());
    }
}
