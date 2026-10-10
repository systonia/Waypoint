<?php

namespace Waypoint\Tests\Integration;

use RuntimeException;
use Waypoint\Exceptions\UnauthorizedException;
use Waypoint\Http\{Request, Response};
use Waypoint\Tests\Fixtures\Controllers\CustomersController;
use Waypoint\Waypoint;

/** Exceptions from $app->use() middleware get the same handlers as a route's: Problem Details, not a fatal error. */
final class AppMiddlewareExceptionTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $app = Waypoint::create();
        $app->use(function (Request $req, Response $res, callable $next): mixed {
            return match ($req->path) {
                '/unauthorized' => throw new UnauthorizedException(),
                '/broken' => throw new RuntimeException('database password in a message'),
                default => $next($req, $res),
            };
        });
        $app->attach([CustomersController::class]);
    }

    public function testAnHttpExceptionFromAnAppMiddlewareBecomesItsProblemDetails(): void
    {
        $output = $this->dispatch('GET', '/unauthorized');

        $this->assertSame(401, $this->lastResponse?->status());
        $this->assertSame('application/problem+json', $this->sentHeaders()['content-type'] ?? null);
        $this->assertSame(401, json_decode($output, true)['status'] ?? null);
    }

    public function testAnyOtherExceptionFromAnAppMiddlewareBecomesAGeneric500WithoutItsMessage(): void
    {
        $output = $this->dispatch('GET', '/broken');

        $this->assertSame(500, $this->lastResponse?->status());
        $this->assertStringNotContainsString('password', $output);
        $this->assertSame('Internal Server Error', json_decode($output, true)['title'] ?? null);
    }
}
