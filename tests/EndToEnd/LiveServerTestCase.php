<?php

namespace Waypoint\Tests\EndToEnd;

use PHPUnit\Framework\TestCase;

/**
 * Boots the real app under PHP's built-in web server (php -S) once for the
 * whole test class and makes genuine HTTP requests against it over a real
 * socket -- as opposed to Tests/Integration, which calls App::handleHttp()
 * in-process within the PHPUnit process itself. That in-process shortcut
 * can't observe things that only exist on a real request cycle: the SAPI's
 * own $_GET population, actual php://input body parsing, headers as
 * actually transmitted on the wire, and (most importantly) that the app
 * re-bootstraps from nothing on every single request, since php -S starts
 * each request with a clean PHP engine state -- there is no shared static
 * state across requests the way there is across test methods in-process.
 */
abstract class LiveServerTestCase extends TestCase
{
    /** @var resource|null */
    private static $process;
    private static string $host = '127.0.0.1';
    private static int $port;

    public static function setUpBeforeClass(): void
    {
        // The front controller uses a fixed cache path (sys_get_temp_dir()
        // . '/e2e-cache') so cache persistence across real, separate
        // requests can be tested at all -- clear it first so the first
        // request in this run deterministically compiles fresh rather than
        // silently reusing a still-valid cache left over from a prior run.
        self::removeDirectory(sys_get_temp_dir() . '/e2e-cache');

        self::$port = self::findFreePort();
        $docroot = __DIR__ . '/server';
        $router = $docroot . '/index.php';

        $command = [PHP_BINARY, '-S', self::$host . ':' . self::$port, '-t', $docroot, $router];
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        self::$process = proc_open($command, $descriptors, $pipes, $docroot);
        if (!is_resource(self::$process)) {
            self::fail('Could not start the built-in PHP server for end-to-end tests.');
        }
        // Never read from these, but they must not be left blocking/full.
        foreach ($pipes as $pipe) {
            stream_set_blocking($pipe, false);
        }

        self::waitUntilListening(5.0);
    }

    public static function tearDownAfterClass(): void
    {
        if (is_resource(self::$process)) {
            proc_terminate(self::$process);
            proc_close(self::$process);
        }
    }

    private static function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = "$dir/$entry";
            is_dir($path) ? self::removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    private static function findFreePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        if ($socket === false) {
            self::fail("Could not find a free port: $errstr");
        }
        $name = stream_socket_get_name($socket, false);
        fclose($socket);
        return (int) substr($name, strrpos($name, ':') + 1);
    }

    private static function waitUntilListening(float $timeoutSeconds): void
    {
        $deadline = microtime(true) + $timeoutSeconds;
        while (microtime(true) < $deadline) {
            $status = proc_get_status(self::$process);
            if (!$status['running']) {
                self::fail('The built-in PHP server exited before it started listening.');
            }
            $conn = @fsockopen(self::$host, self::$port, $errno, $errstr, 0.2);
            if ($conn) {
                fclose($conn);
                return;
            }
            usleep(50_000);
        }
        self::fail('The built-in PHP server did not start listening in time.');
    }

    protected function baseUrl(): string
    {
        return 'http://' . self::$host . ':' . self::$port;
    }

    /**
     * Makes a real HTTP request against the live server.
     *
     * @param array{headers?: array<string,string>, body?: string, json?: mixed} $options
     * @return array{status: int, headers: array<string,string>, body: string}
     */
    protected function request(string $method, string $path, array $options = []): array
    {
        $headers = $options['headers'] ?? [];

        $ch = curl_init($this->baseUrl() . $path);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);

        if (array_key_exists('json', $options)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($options['json']));
            $headers['Content-Type'] = 'application/json';
        } elseif (isset($options['body'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $options['body']);
        }

        if ($headers) {
            $headerLines = [];
            foreach ($headers as $name => $value) {
                $headerLines[] = "$name: $value";
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headerLines);
        }

        $raw = curl_exec($ch);
        if ($raw === false) {
            $error = curl_error($ch);
            $this->fail("curl request to $path failed: $error");
        }

        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

        $rawHeaders = substr($raw, 0, $headerSize);
        $body = substr($raw, $headerSize);

        $responseHeaders = [];
        foreach (explode("\r\n", $rawHeaders) as $line) {
            if (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $responseHeaders[strtolower(trim($name))] = trim($value);
            }
        }

        return ['status' => $status, 'headers' => $responseHeaders, 'body' => $body];
    }
}
