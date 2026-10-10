<?php

namespace Waypoint\Tests\EndToEnd;

/**
 * The whole live suite again, with the same front controller under nope instead of php -S: one process
 * serves every request through Waypoint\Nope\Server. Skipped without nope; NOPE_BIN points at the binary
 * when it isn't on the PATH.
 */
final class NopeLiveTest extends FullAppLiveTest
{
    public static function setUpBeforeClass(): void
    {
        if (self::nope() === null) {
            self::markTestSkipped('nope not found: set NOPE_BIN or put nope on the PATH');
        }
        parent::setUpBeforeClass();
    }

    protected static function serverCommand(string $host, int $port, string $docroot, string $router): array
    {
        // One worker: the interleaving test needs both requests in the same process
        return [(string) self::nope(), '--workers', '1', $router];
    }

    private static function nope(): ?string
    {
        $configured = getenv('NOPE_BIN');
        if (is_string($configured) && is_executable($configured)) {
            return $configured;
        }
        foreach (explode(PATH_SEPARATOR, (string) getenv('PATH')) as $dir) {
            if ($dir !== '' && is_executable("$dir/nope")) {
                return "$dir/nope";
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
            $handle = curl_init($this->baseUrl() . "/nope/interleaved?name=$name");
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
}
