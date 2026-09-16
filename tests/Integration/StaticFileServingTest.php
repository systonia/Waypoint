<?php

namespace Waypoint\Tests\Integration;

use Waypoint\Waypoint;
use Waypoint\Options\FileSystemOptions;

final class StaticFileServingTest extends IntegrationTestCase
{
    private string $publicDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->publicDir = __DIR__ . '/../Fixtures/Public';
        $app = Waypoint::create();
        $app->configure(function (FileSystemOptions $fs) {
            $fs->publicDirectory = $this->publicDir;
        });
        $app->attach([]);
    }

    public function testServesAKnownExtensionWithItsMappedMimeType(): void
    {
        $output = $this->dispatch('GET', '/style.css');

        $this->assertSame("body { color: red; }\n", $output);
        $this->assertStringStartsWith('text/css', $this->sentHeaders()['content-type'] ?? '');
    }

    public function testServesAnUnmappedExtensionUsingMimeContentTypeFallback(): void
    {
        $output = $this->dispatch('GET', '/notes.txt');

        $this->assertSame("plain text asset\n", $output);
        $this->assertNotNull($this->sentHeaders()['content-type'] ?? null);
    }

    public function testSetsAContentLengthHeaderMatchingTheFileSize(): void
    {
        $this->dispatch('GET', '/style.css');

        $this->assertSame(
            (string) filesize($this->publicDir . '/style.css'),
            $this->sentHeaders()['content-length'] ?? null
        );
    }

    public function testFallsThroughToRoutingWhenFileDoesNotExist(): void
    {
        $output = $this->dispatch('GET', '/does-not-exist.css');
        $this->assertSame(['error' => 'Not found'], json_decode($output, true));
    }

    public function testPathTraversalOutsideThePublicDirectoryIsRejectedEvenWhenTheTargetFileExists(): void
    {
        // Tests/Fixtures/Env/.env genuinely exists one directory above the
        // public root -- proving the containment check (not just a missing
        // file) is what blocks this.
        $output = $this->dispatch('GET', '/../Env/.env');
        $this->assertSame(['error' => 'Not found'], json_decode($output, true));
    }

}
