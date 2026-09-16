<?php

namespace Waypoint\Testing;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use Waypoint\App;
use Waypoint\Waypoint;

/**
 * Base class for app and plugin tests: a fresh Waypoint per test, clean
 * superglobals, and a request builder that drives App::handleHttp() the way
 * a real SAPI would.
 *
 *   $this->post('/login', ['email' => 'a@b.de', 'password' => 'x'])->assertStatus(200)->assertSee('Invalid');
 *   $this->request('GET', '/dashboard')->withJwt(['sub' => '1'])->send()->assertOk();
 */
abstract class TestCase extends PHPUnitTestCase
{
    /** @var list<string> */
    private array $tempDirs = [];

    protected function setUp(): void
    {
        Waypoint::reset();
        self::resetGlobals();
    }

    protected function tearDown(): void
    {
        Waypoint::reset();
        self::resetGlobals();
        foreach ($this->tempDirs as $dir) {
            self::removeDirectory($dir);
        }
        $this->tempDirs = [];
    }

    /** The app under test (created on first use). Configure and attach it here or in setUp(). */
    protected function app(): App
    {
        return Waypoint::create();
    }

    protected function request(string $method, string $uri): TestRequest
    {
        return new TestRequest($method, $uri);
    }

    /** @param array<string, string> $headers */
    protected function get(string $uri, array $headers = []): TestResponse
    {
        return $this->request('GET', $uri)->withHeaders($headers)->send();
    }

    /**
     * @param array<array-key, mixed> $body Form fields.
     * @param array<string, string> $headers
     */
    protected function post(string $uri, array $body = [], array $headers = []): TestResponse
    {
        return $this->request('POST', $uri)->withBody($body)->withHeaders($headers)->send();
    }

    /**
     * A JSON request: $data is sent as the raw body with Content-Type: application/json.
     * @param array<array-key, mixed> $data
     * @param array<string, string> $headers
     */
    protected function json(string $method, string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->request($method, $uri)->withJson($data)->withHeaders($headers)->send();
    }

    /** A fresh empty directory, removed after the test -- e.g. for FileSystemOptions::$cacheDirectory. */
    protected function tempDir(): string
    {
        $dir = sys_get_temp_dir() . '/waypoint-test-' . bin2hex(random_bytes(6));
        mkdir($dir, 0777, true);
        $this->tempDirs[] = $dir;
        return $dir;
    }

    private static function resetGlobals(): void
    {
        $_GET = [];
        $_POST = [];
        $_COOKIE = [];
        foreach (array_keys($_SERVER) as $key) {
            if (str_starts_with($key, 'HTTP_')) {
                unset($_SERVER[$key]);
            }
        }
        unset($_SERVER['AUTHORIZATION']);
    }

    private static function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = "$dir/$entry";
            is_dir($path) ? self::removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
