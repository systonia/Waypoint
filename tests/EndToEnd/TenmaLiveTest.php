<?php

namespace Waypoint\Tests\EndToEnd;

/**
 * The whole live suite again, with the same front controller under Tenma instead of php -S: one process
 * serves every request, with the app itself as Tenma's handler. Skipped without Tenma; TENMA_BIN points at the binary
 * when it isn't on the PATH.
 */
final class TenmaLiveTest extends FullAppLiveTest
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
}
