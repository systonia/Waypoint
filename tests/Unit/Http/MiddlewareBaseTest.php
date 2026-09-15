<?php

namespace Waypoint\Tests\Unit\Http;

use PHPUnit\Framework\TestCase;
use Waypoint\Http\{MiddlewareBase, Request, Response};

final class PlainMiddleware extends MiddlewareBase
{
    // No before()/after() overrides -- exercises MiddlewareBase's own
    // default pass-through implementations directly.
}

final class VetoingMiddleware extends MiddlewareBase
{
    protected function before(Request $req, Response $res): bool
    {
        return false;
    }
}

final class MiddlewareBaseTest extends TestCase
{
    private array $serverBackup;

    protected function setUp(): void
    {
        $this->serverBackup = $_SERVER;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;
    }

    public function testHandleIsFinalAndCannotBeOverridden(): void
    {
        $reflection = new \ReflectionMethod(MiddlewareBase::class, 'handle');
        $this->assertTrue($reflection->isFinal());
    }

    public function testDefaultBeforeAndAfterAreANoOpPassThroughToNext(): void
    {
        $middleware = new PlainMiddleware();
        $req = Request::capture();
        $res = new Response();

        $called = false;
        $result = $middleware->handle($req, $res, function (Request $r, Response $s) use (&$called): string {
            $called = true;
            return 'next-ran';
        });

        $this->assertTrue($called);
        $this->assertSame('next-ran', $result);
    }

    public function testBeforeReturningFalseVetoesTheRequestAndSkipsNext(): void
    {
        $middleware = new VetoingMiddleware();
        $req = Request::capture();
        $res = new Response();

        $called = false;
        $result = $middleware->handle($req, $res, function (Request $r, Response $s) use (&$called): string {
            $called = true;
            return 'next-ran';
        });

        $this->assertFalse($called);
        $this->assertNull($result);
    }
}
