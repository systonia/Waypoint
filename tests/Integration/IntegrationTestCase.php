<?php

namespace Waypoint\Tests\Integration;

use Waypoint\Testing\{TestCase, TestResponse};
use Waypoint\Tests\Fixtures\Support\CallTracker;

/**
 * The framework's own integration base: the shipped Waypoint\Testing kit plus
 * the fixtures' CallTracker reset. dispatch() is the older body-only helper the
 * bulk of the suite still uses; new tests use the kit's request builder directly.
 */
abstract class IntegrationTestCase extends TestCase
{
    protected ?TestResponse $lastResponse = null;

    protected function setUp(): void
    {
        parent::setUp();
        CallTracker::reset();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        CallTracker::reset();
    }

    /**
     * Runs one request and returns the body; headers/status are on $this->lastResponse and sentHeaders().
     * @param array<string, string> $headers
     * @param array<array-key, mixed> $body
     */
    protected function dispatch(string $method, string $uri, array $headers = [], array $body = []): string
    {
        $this->lastResponse = $this->request($method, $uri)->withHeaders($headers)->withBody($body)->send();
        return $this->lastResponse->body();
    }

    /** @return array<string, string> lower-cased header name => value of the last dispatch() */
    protected function sentHeaders(): array
    {
        $headers = $this->lastResponse?->headers() ?? [];
        // Older tests read Set-Cookie through here too; the kit keeps cookies apart.
        $cookies = $this->lastResponse?->cookies() ?? [];
        if ($cookies !== []) {
            $headers['set-cookie'] = $cookies[count($cookies) - 1];
        }
        return $headers;
    }
}
