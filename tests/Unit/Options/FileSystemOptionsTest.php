<?php

namespace Waypoint\Tests\Unit\Options;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Waypoint\Options\FileSystemOptions;

final class FileSystemOptionsTest extends TestCase
{
    private ?string $originalScriptFilename;

    protected function setUp(): void
    {
        $this->originalScriptFilename = $_SERVER['SCRIPT_FILENAME'] ?? null;
        $_SERVER['SCRIPT_FILENAME'] = __FILE__;
    }

    protected function tearDown(): void
    {
        if ($this->originalScriptFilename !== null) {
            $_SERVER['SCRIPT_FILENAME'] = $this->originalScriptFilename;
        } else {
            unset($_SERVER['SCRIPT_FILENAME']);
        }
    }

    public function testBuildPathReturnsAbsolutePathsUnchanged(): void
    {
        $opts = new FileSystemOptions();
        $this->assertSame('/tmp/some-dir', $opts->buildPath('/tmp/some-dir'));
        $this->assertSame('/tmp/some-dir', $opts->buildPath('/tmp/some-dir/'));
    }

    public function testBuildPathResolvesRelativePathsAgainstTheScriptDirectory(): void
    {
        $opts = new FileSystemOptions();
        $this->assertSame(dirname(realpath(__FILE__)) . '/views', $opts->buildPath('views'));
    }

    public function testCacheDirectoryDefaultsToSystemTempWhenUnconfigured(): void
    {
        $opts = new FileSystemOptions();
        $this->assertSame(sys_get_temp_dir() . '/cache', $opts->getCacheDirectory());
        $this->assertNull($opts->cacheDirectory);
    }

    public function testAssigningCacheDirectoryNormalizesItAndReportsThePath(): void
    {
        $opts = new FileSystemOptions();
        $opts->cacheDirectory = '/tmp/test-cache';

        $this->assertSame('/tmp/test-cache', $opts->cacheDirectory);
        $this->assertSame('/tmp/test-cache', $opts->getCacheDirectory());
    }

    public function testAssigningCacheDirectoryResolvesARelativePathAgainstTheScriptDirectory(): void
    {
        $opts = new FileSystemOptions();
        $opts->cacheDirectory = 'var/cache';

        $this->assertSame(dirname(realpath(__FILE__)) . '/var/cache', $opts->cacheDirectory);
    }

    public function testGetPublicDirectoryThrowsWhenNeverConfigured(): void
    {
        $opts = new FileSystemOptions();
        $this->expectException(RuntimeException::class);
        $opts->getPublicDirectory();
    }

    public function testAssigningPublicDirectoryNormalizesItAndReportsThePath(): void
    {
        $opts = new FileSystemOptions();
        $opts->publicDirectory = '/tmp/test-public';

        $this->assertSame('/tmp/test-public', $opts->publicDirectory);
        $this->assertSame('/tmp/test-public', $opts->getPublicDirectory());
    }

    public function testCacheValidateDefaultsToTrue(): void
    {
        $opts = new FileSystemOptions();
        $this->assertTrue($opts->cacheValidate);
    }

    public function testCacheValidateCanBeDisabled(): void
    {
        $opts = new FileSystemOptions();
        $opts->cacheValidate = false;
        $this->assertFalse($opts->cacheValidate);
    }

    public function testAssetsPathDefaultsToAssets(): void
    {
        $opts = new FileSystemOptions();
        $this->assertSame('/assets', $opts->assetsPath);
    }

    public function testAssigningAssetsPathNormalizesLeadingAndTrailingSlashes(): void
    {
        $opts = new FileSystemOptions();
        $opts->assetsPath = 'static/';

        $this->assertSame('/static', $opts->assetsPath);
    }

    public function testAssigningAssetsPathWithoutAnyTrailingSlashStillNormalizes(): void
    {
        $opts = new FileSystemOptions();
        $opts->assetsPath = '/cdn';

        $this->assertSame('/cdn', $opts->assetsPath);
    }
}
