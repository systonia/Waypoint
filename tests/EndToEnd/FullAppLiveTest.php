<?php

namespace Waypoint\Tests\EndToEnd;

use PHPUnit\Framework\Attributes\DataProvider;
use Waypoint\{Waypoint, JWT};
use Waypoint\Options\JWTOptions;

/**
 * Exercises the app as a real running server (see LiveServerTestCase).
 * Tests/Integration already covers the framework's logic exhaustively
 * in-process; these focus specifically on what only a real request cycle
 * can prove -- the SAPI's own query-string/body handling, headers as
 * actually transmitted, and cross-process route-cache reuse.
 */
final class FullAppLiveTest extends LiveServerTestCase
{
    /** Signs a token with the same secret Tests/EndToEnd/server/index.php configures, without touching the live server's own state (it's a separate process). */
    private function signToken(array $payload): string
    {
        Waypoint::reset();
        Waypoint::create()->configure(function (JWTOptions $opts) {
            $opts->secret = 'e2e-test-secret';
        });
        $token = JWT::encode($payload);
        Waypoint::reset();
        return $token;
    }

    public function testStaticRouteOverRealHttp(): void
    {
        $response = $this->request('POST', '/orders');

        $this->assertSame(200, $response['status']);
        $this->assertSame('"pong"', $response['body']);
    }

    public function testRealQueryStringIsParsedByTheSapiItself(): void
    {
        // Unlike Tests/Integration, nothing here manually populates $_GET --
        // this proves PHP's own built-in server does it correctly for a
        // route that binds both a path placeholder and a query parameter.
        $response = $this->request('GET', '/customers/1/orders?status=open');

        $this->assertSame(200, $response['status']);
        $this->assertSame(['customerId' => '1', 'status' => 'open'], json_decode($response['body'], true));
    }

    public function testRealJsonRequestBodyIsParsedFromPhpInput(): void
    {
        // Tests/Integration can't exercise this branch at all: php://input
        // is always empty under the PHPUnit CLI process, so Request::capture()
        // needed an injection hook there. Over a real socket, this is the
        // actual, unmodified code path a real API client would hit.
        $response = $this->request('POST', '/products', [
            'json' => ['name' => 'Widget', 'sku' => 'AB-12'],
        ]);

        $this->assertSame(200, $response['status']);
        $this->assertSame(['name' => 'Widget', 'sku' => 'AB-12'], json_decode($response['body'], true));
    }

    public function testInvalidJsonBodyStillProducesValidationErrors(): void
    {
        $response = $this->request('POST', '/products', [
            'json' => ['name' => '', 'sku' => 'x'],
        ]);

        $decoded = json_decode($response['body'], true);
        $this->assertSame(422, $response['status']);
        $this->assertSame('Validation failed', $decoded['title']);
    }

    public function testCorsPreflightHeadersAsActuallyTransmitted(): void
    {
        $response = $this->request('OPTIONS', '/orders', [
            'headers' => ['Origin' => 'https://example.com'],
        ]);

        $this->assertSame(204, $response['status']);
        $this->assertSame('*', $response['headers']['access-control-allow-origin'] ?? null);
        $this->assertSame('GET, POST, OPTIONS', $response['headers']['access-control-allow-methods'] ?? null);
    }

    public function testJwtRoundTripsThroughARealAuthorizationHeader(): void
    {
        $token = $this->signToken(['sub' => 'e2e-user']);

        $response = $this->request('GET', '/whoami', [
            'headers' => ['Authorization' => "Bearer $token"],
        ]);

        $this->assertSame('e2e-user', json_decode($response['body'], true)['jwt']['sub']);
    }

    public function testMissingAuthorizationLeavesJwtNull(): void
    {
        $response = $this->request('GET', '/whoami');
        $this->assertNull(json_decode($response['body'], true)['jwt']);
    }

    /** @param array<string, mixed> $expectedProblem */
    #[DataProvider('guardedExceptionProvider')]
    public function testExceptionsMapToRealHttpStatusCodes(string $path, int $expectedStatus, array $expectedProblem): void
    {
        $response = $this->request('GET', $path);

        $this->assertSame($expectedStatus, $response['status']);
        $this->assertSame($expectedProblem, json_decode($response['body'], true));
    }

    /** @return array<string, array{string, int, array<string, mixed>}> */
    public static function guardedExceptionProvider(): array
    {
        return [
            'forbidden' => [
                '/guarded/forbidden',
                403,
                ['type' => 'about:blank', 'title' => 'Forbidden', 'status' => 403],
            ],
            'unauthorized' => [
                '/guarded/unauthorized',
                401,
                ['type' => 'about:blank', 'title' => 'Unauthorized', 'status' => 401],
            ],
            'not found (custom message)' => [
                '/guarded/missing',
                404,
                ['type' => 'about:blank', 'title' => 'Not Found', 'status' => 404, 'detail' => 'Widget not found'],
            ],
        ];
    }

    public function testUnroutedPathReturns404(): void
    {
        $response = $this->request('GET', '/this/route/does/not/exist');

        $this->assertSame(404, $response['status']);
        $this->assertSame(['error' => 'Not found'], json_decode($response['body'], true));
    }

    public function testMiddlewareStackRunsBeforeTheControllerOverRealDispatch(): void
    {
        $response = $this->request('GET', '/middleware/stacked');

        $this->assertSame('yes', $response['headers']['x-traced'] ?? null);
        $this->assertSame(['ok' => true], json_decode($response['body'], true));
    }

    public function testShortCircuitingMiddlewareBlocksTheControllerOverRealDispatch(): void
    {
        $response = $this->request('GET', '/middleware/blocked');

        $this->assertSame(403, $response['status']);
        $this->assertSame(['error' => 'blocked by middleware'], json_decode($response['body'], true));
    }

    public function testStaticAssetIsServedFromThePublicDirectory(): void
    {
        $response = $this->request('GET', '/style.css');

        $this->assertSame(200, $response['status']);
        $this->assertStringStartsWith('text/css', $response['headers']['content-type'] ?? '');
        $this->assertSame("body { color: red; }\n", $response['body']);
    }

    public function testOpenApiSpecIsServedOverRealHttp(): void
    {
        $response = $this->request('GET', '/openapi/spec.json');
        $spec = json_decode($response['body'], true);

        $this->assertSame(200, $response['status']);
        $this->assertSame('3.1.0', $spec['openapi']);
        $this->assertArrayHasKey('/customers', $spec['paths']);
    }

    public function testRouteCachePersistsAcrossSeparateRealRequests(): void
    {
        // Each request to php -S is a genuinely fresh PHP engine state --
        // this is the one thing Tests/Integration structurally cannot
        // verify, since its whole suite shares one PHP process.
        $cacheDir = sys_get_temp_dir() . '/e2e-cache';

        $first = $this->request('GET', '/orders/1');
        $this->assertSame(200, $first['status']);
        $this->assertFileExists("$cacheDir/routes.php");
        $this->assertFileExists("$cacheDir/meta.php");

        // A second, later request must still dispatch correctly whether it
        // loaded from that now-existing cache or recompiled.
        $second = $this->request('GET', '/orders/1');
        $this->assertSame($first['body'], $second['body']);
    }
}
