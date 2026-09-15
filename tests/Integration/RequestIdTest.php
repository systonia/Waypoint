<?php

namespace Waypoint\Tests\Integration;

use Psr\Log\LogLevel;
use Waypoint\{Waypoint, Logger};
use Waypoint\Options\LoggerOptions;
use Waypoint\Tests\Fixtures\Controllers\{WhoAmIController, LoggingController};
use Waypoint\Tests\Fixtures\Support\DummyLogger;

/**
 * Request::$id / Response's X-Request-ID header / Logger's automatic
 * 'request_id' context -- see Request::capture()'s own doc for the
 * resolution order (X-Request-Id header, X-Correlation-Id fallback, else
 * a generated UUID v4) and RequestContext for how Logger picks it up
 * without any call site passing it in by hand.
 */
final class RequestIdTest extends IntegrationTestCase
{
    public function testResponseEchoesBackAGeneratedIdWhenNoHeaderWasSent(): void
    {
        $app = Waypoint::create();
        $app->attach([WhoAmIController::class]);

        $output = $this->dispatch('GET', '/whoami');

        $id = json_decode($output, true)['id'];
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $id
        );
        $this->assertSame($id, $this->sentHeaders()['x-request-id'] ?? null);
    }

    public function testResponseEchoesBackTheIncomingXRequestIdHeaderVerbatim(): void
    {
        $app = Waypoint::create();
        $app->attach([WhoAmIController::class]);

        $output = $this->dispatch('GET', '/whoami', ['X-Request-Id' => 'upstream-not-a-uuid']);

        $this->assertSame('upstream-not-a-uuid', json_decode($output, true)['id']);
        $this->assertSame('upstream-not-a-uuid', $this->sentHeaders()['x-request-id'] ?? null);
    }

    public function testResponseFallsBackToXCorrelationIdWhenNoXRequestIdIsSent(): void
    {
        $app = Waypoint::create();
        $app->attach([WhoAmIController::class]);

        $output = $this->dispatch('GET', '/whoami', ['X-Correlation-Id' => 'correlation-xyz']);

        $this->assertSame('correlation-xyz', json_decode($output, true)['id']);
        $this->assertSame('correlation-xyz', $this->sentHeaders()['x-request-id'] ?? null);
    }

    public function testLoggerAutomaticallyStampsTheCurrentRequestIdIntoLogContext(): void
    {
        $dummy = new DummyLogger();
        $app = Waypoint::create();
        // Scoped to INFO only: LoggingController's route is deliberately
        // unversioned, which RouteCompiler itself logs a WARNING about at
        // attach()-time (compile-time, before any request/RequestContext
        // exists) -- scoping keeps this test about the one log call that
        // actually happens *during* the dispatched request.
        $app->configure(function (LoggerOptions $opts) use ($dummy) {
            $opts->add($dummy, [LogLevel::INFO]);
        });
        $app->attach([LoggingController::class]);

        $this->dispatch('GET', '/logging/ping', ['X-Request-Id' => 'trace-me-through-the-logs']);

        $this->assertCount(1, $dummy->logs);
        $this->assertSame('handled ping', $dummy->logs[0][1]);
        $this->assertSame('trace-me-through-the-logs', $dummy->logs[0][2]['request_id'] ?? null);
    }

    public function testLoggerContextDoesNotLeakARequestIdOutsideOfARequest(): void
    {
        $dummy = new DummyLogger();
        $app = Waypoint::create();
        $app->configure(function (LoggerOptions $opts) use ($dummy) {
            $opts->add($dummy);
        });

        // No dispatch() at all -- RequestContext was never set (and
        // handleHttp()'s finally already cleared it after any prior
        // request in this process), so there's nothing to stamp.
        (new Logger())->info('no request in flight');

        $this->assertArrayNotHasKey('request_id', $dummy->logs[0][2]);
    }

    public function testRequestIdDoesNotLeakFromOneDispatchIntoTheNext(): void
    {
        $dummy = new DummyLogger();
        $app = Waypoint::create();
        $app->configure(function (LoggerOptions $opts) use ($dummy) {
            $opts->add($dummy, [LogLevel::INFO]);
        });
        $app->attach([LoggingController::class]);

        $this->dispatch('GET', '/logging/ping', ['X-Request-Id' => 'first-request']);
        $this->dispatch('GET', '/logging/ping', ['X-Request-Id' => 'second-request']);

        $this->assertCount(2, $dummy->logs);
        $this->assertSame('first-request', $dummy->logs[0][2]['request_id'] ?? null);
        $this->assertSame('second-request', $dummy->logs[1][2]['request_id'] ?? null);
    }

    public function testAnExplicitRequestIdInContextIsNeverOverwritten(): void
    {
        $dummy = new DummyLogger();
        $app = Waypoint::create();
        $app->configure(function (LoggerOptions $opts) use ($dummy) {
            $opts->add($dummy);
        });

        (new Logger())->info('manual override', ['request_id' => 'caller-supplied']);

        $this->assertSame('caller-supplied', $dummy->logs[0][2]['request_id']);
    }

    /** @return array<string, string> */
    private function sentHeaders(): array
    {
        $raw = function_exists('xdebug_get_headers') ? xdebug_get_headers() : headers_list();
        $headers = [];
        foreach ($raw as $line) {
            [$name, $value] = array_map('trim', explode(':', $line, 2) + [1 => '']);
            $headers[strtolower($name)] = $value;
        }
        return $headers;
    }
}
