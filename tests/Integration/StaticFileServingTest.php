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

    public function testOnlyGetAndHeadReadAFile(): void
    {
        $this->assertSame(200, $this->request('HEAD', '/style.css')->send()->status());
        foreach (['POST', 'PUT', 'DELETE'] as $method) {
            $this->assertSame(404, $this->request($method, '/style.css')->send()->status(), $method);
        }
    }

    public function testAPercentEncodedPathFindsItsFileButAnEncodedSlashNeverMakesASegment(): void
    {
        $this->assertSame("spaced out\n", $this->dispatch('GET', '/my%20file.txt'));
        $this->assertSame(404, $this->request('GET', '/assets%2F..%2Fnotes.txt')->send()->status());
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

    public function testServesFilesInSubdirectoriesOfThePublicDirectory(): void
    {
        $this->assertSame("nested asset\n", $this->dispatch('GET', '/assets/app.js'));
    }

    /** @return iterable<string, array{string}> */
    public static function neverServed(): iterable
    {
        yield 'PHP source of the front controller' => ['/index.php'];
        yield 'a dotfile' => ['/.env'];
        yield 'a file in a dot-directory' => ['/.hidden/config'];
        yield 'a sibling directory sharing the name prefix' => ['/../Public-backup/secret.txt'];
        yield 'a dot-segment that stays inside' => ['/./style.css'];
        yield 'a symlinked directory pointing outside' => ['/linked-env/config.json'];
        yield 'a symlink with a harmless name pointing at PHP source' => ['/innocent.txt'];
        yield 'a backslash' => ['/assets\\app.js'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('neverServed')]
    public function testNeverServesAnythingOutsideThePublicDirectoryDotfilesOrPhpSource(string $path): void
    {
        $output = $this->dispatch('GET', $path);
        $this->assertSame(['error' => 'Not found'], json_decode($output, true), "served $path");
    }

}
