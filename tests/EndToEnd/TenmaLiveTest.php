<?php

namespace Waypoint\Tests\EndToEnd;

/**
 * The whole live suite again, with the same front controller under Tenma instead of php -S: one process
 * serves every request, with the app itself as Tenma's handler. Skipped without Tenma; TENMA_BIN points at the binary
 * when it isn't on the PATH.
 */
class TenmaLiveTest extends FullAppLiveTest
{
    public static function setUpBeforeClass(): void
    {
        if (self::tenma() === null) {
            self::markTestSkipped('Tenma not found: set TENMA_BIN or put tenma on the PATH');
        }
        parent::setUpBeforeClass();
    }

    protected static function serverCommand(string $host, int $port, string $docroot, string $router): array
    {
        // One worker: the interleaving test needs both requests in the same process
        return [(string) self::tenma(), '--workers', '1', $router];
    }

    private static function tenma(): ?string
    {
        $configured = getenv('TENMA_BIN');
        if (is_string($configured) && is_executable($configured)) {
            return $configured;
        }
        foreach (explode(PATH_SEPARATOR, (string) getenv('PATH')) as $dir) {
            if ($dir !== '' && is_executable("$dir/tenma")) {
                return "$dir/tenma";
            }
        }
        return null;
    }

    public function testInterleavedRequestsKeepTheirRequestAndRequestIdIntoChildTasks(): void
    {
        // Both at once: they wait side by side in one worker, each with a child task of its own
        $multi = curl_multi_init();
        $handles = [];
        foreach (['ada', 'bo'] as $name) {
            $handle = curl_init($this->baseUrl() . "/tenma/interleaved?name=$name");
            curl_setopt_array($handle, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5, CURLOPT_HTTPHEADER => ["X-Request-Id: id-$name"]]);
            curl_multi_add_handle($multi, $handle);
            $handles[$name] = $handle;
        }
        $started = microtime(true);
        do {
            curl_multi_exec($multi, $running);
            curl_multi_select($multi, 0.05);
        } while ($running > 0);

        foreach ($handles as $name => $handle) {
            $this->assertSame(
                ['name' => $name, 'requestId' => "id-$name", 'childRequestId' => "id-$name"],
                json_decode((string) curl_multi_getcontent($handle), true),
            );
        }
        // Waiting side by side, not one after the other (2 x 0.2 s)
        $this->assertLessThan(0.39, microtime(true) - $started);
    }

    public function testARouteHandsTheRequestOverToATenmaWebSocket(): void
    {
        $url = parse_url($this->baseUrl());
        $socket = stream_socket_client("tcp://{$url['host']}:{$url['port']}", timeout: 5);
        $this->assertNotFalse($socket);
        stream_set_timeout($socket, 5);
        fwrite($socket, "GET /tenma/echo HTTP/1.1\r\nHost: {$url['host']}\r\nUpgrade: websocket\r\nConnection: Upgrade\r\n"
            . "Sec-WebSocket-Key: " . base64_encode(random_bytes(16)) . "\r\nSec-WebSocket-Version: 13\r\nX-Request-Id: id-ws\r\n\r\n");
        $head = '';
        while (!str_contains($head, "\r\n\r\n") && ($line = fgets($socket)) !== false) {
            $head .= $line;
        }
        $this->assertStringStartsWith('HTTP/1.1 101', $head);

        // One masked text frame up (client frames must be masked), one unmasked frame back
        $mask = random_bytes(4);
        $payload = 'hello';
        fwrite($socket, "\x81" . chr(0x80 | strlen($payload)) . $mask . ($payload ^ str_repeat($mask, 2)));
        $header = (string) fread($socket, 2);
        $reply = (string) fread($socket, ord($header[1]) & 0x7f);
        fclose($socket);

        $this->assertSame("\x81", $header[0]);
        $this->assertSame('id-ws: hello', $reply);
    }

    public function testAHandedOverStreamKeepsTheAppsHeadersAndCookies(): void
    {
        $handle = curl_init($this->baseUrl() . '/tenma/events');
        curl_setopt_array($handle, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_TIMEOUT => 5, CURLOPT_HTTPHEADER => ['X-Request-Id: id-sse']]);
        $response = (string) curl_exec($handle);

        $this->assertMatchesRegularExpression('/^content-type: text\/event-stream\r$/mi', $response);
        $this->assertMatchesRegularExpression('/^x-request-id: id-sse\r$/mi', $response);
        $this->assertMatchesRegularExpression('/^set-cookie: seen=1;/mi', $response);
        $this->assertStringContainsString("data: hello\n\n", $response);
    }
}
