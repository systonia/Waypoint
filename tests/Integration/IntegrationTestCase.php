<?php

namespace Waypoint\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Waypoint\Waypoint;
use Waypoint\Tests\Fixtures\Support\CallTracker;

/**
 * Base for tests that exercise the framework end to end through its static
 * process-wide singleton (Waypoint), reset before and after each test so
 * integration tests never leak state into each other or into the unit
 * suite. FileSystem, Logger and Environment are no longer among these: all
 * three are plain container-resolved Options-driven classes (see
 * App::configure()), so each fresh App (Waypoint::create() after
 * Waypoint::reset()) gets its own fresh FileSystemOptions/LoggerOptions/
 * EnvironmentOptions automatically -- nothing to reset explicitly.
 */
abstract class IntegrationTestCase extends TestCase
{
    protected function setUp(): void
    {
        Waypoint::reset();
        CallTracker::reset();
    }

    protected function tearDown(): void
    {
        Waypoint::reset();
        CallTracker::reset();
    }

    /**
     * Simulates one HTTP request against the currently-attached app and
     * returns whatever was echoed to the response body.
     *
     * Request::capture() reads $_GET directly rather than re-parsing
     * REQUEST_URI's query string (that's how a real SAPI populates it, but
     * nothing does that for us here), and falls back to $_POST for the body
     * since php://input is always empty under a plain CLI test run -- so
     * both are derived/set explicitly rather than left to PHP to figure out.
     */
    protected function dispatch(string $method, string $uri, array $headers = [], array $body = []): string
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = $uri;

        parse_str((string) parse_url($uri, PHP_URL_QUERY), $_GET);
        $_POST = $body;

        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                unset($_SERVER[$key]);
            }
        }
        foreach ($headers as $name => $value) {
            $_SERVER['HTTP_' . strtoupper(str_replace('-', '_', $name))] = $value;
        }

        // PHP tracks "sent" headers process-wide even under the CLI SAPI,
        // across the whole test run -- clear them so a test asserting a
        // header is absent only ever sees headers *this* dispatch() sent.
        header_remove();

        ob_start();
        // Not run(): that branches on php_sapi_name(), which reports 'cli'
        // under PHPUnit too and would route into the CLI task-runner path
        // (reading *this process's* real argv). handleHttp() is the HTTP
        // path directly, regardless of SAPI.
        Waypoint::create()->handleHttp();
        return ob_get_clean();
    }
}
