<?php

namespace Waypoint\Tests\Integration;

use RuntimeException;
use Waypoint\{Waypoint, FileSystem, Router};
use Waypoint\Options\{FileSystemOptions, RendererOptions};
use Waypoint\Tests\Fixtures\Controllers\{CustomersController, OrdersController};

final class FileSystemCacheTest extends IntegrationTestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        // A dedicated, unique directory per test -- never the shared default
        // (sys_get_temp_dir() . '/cache'), so these tests can never
        // collide with each other or with any other Router construction
        // that falls back to that default.
        $this->tempDir = sys_get_temp_dir() . '/test-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
        parent::tearDown();
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = "$dir/$entry";
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    /** A FileSystemOptions pointed at $this->tempDir, plus the FileSystem that reads/writes through it. */
    private function fileSystem(bool $validate = true): array
    {
        $options = new FileSystemOptions();
        $options->cacheDirectory = $this->tempDir;
        $options->cacheValidate = $validate;
        return [$options, new FileSystem($options)];
    }

    /** A Router built against the given FileSystemOptions, with no container (RendererOptions stays unresolved). */
    private function router(array $controllers, array $services, FileSystemOptions $options): Router
    {
        return new Router($controllers, $services, null, null, $options);
    }

    public function testIsAvailableIsFalseBeforeAnythingIsStored(): void
    {
        [, $fileSystem] = $this->fileSystem();

        $this->assertFalse($fileSystem->isAvailable([CustomersController::class]));
    }

    public function testStoreThenLoadRoundTripsCompiledRoutePlans(): void
    {
        [$options, $fileSystem] = $this->fileSystem();
        $router = $this->router([CustomersController::class], [], $options);

        $this->assertTrue($fileSystem->isAvailable([CustomersController::class]));
        $this->assertFileExists($this->tempDir . '/routes.php');
        $this->assertFileExists($this->tempDir . '/meta.php');
        $this->assertFileExists($this->tempDir . '/attributes.php');

        // A second Router for the same controller set must come back
        // functionally identical whether it recompiled or loaded from cache.
        $cached = $this->router([CustomersController::class], [], $options);
        $this->assertEquals($router->getRoutes(), $cached->getRoutes());
    }

    public function testIsAvailableInvalidatesWhenTheControllerSetShrinks(): void
    {
        [$options, $fileSystem] = $this->fileSystem();
        $this->router([CustomersController::class, OrdersController::class], [], $options);

        // A cache built for two controllers must not be considered valid
        // for just one of them -- otherwise the removed controller's routes
        // would keep being served from the stale cache.
        $this->assertFalse($fileSystem->isAvailable([CustomersController::class]));
    }

    public function testIsAvailableInvalidatesWhenTheControllerSetGrows(): void
    {
        [$options, $fileSystem] = $this->fileSystem();
        $this->router([CustomersController::class], [], $options);

        $this->assertFalse($fileSystem->isAvailable([CustomersController::class, OrdersController::class]));
    }

    public function testLoadAttributesReturnsEmptyArrayWhenNothingWasStoredYet(): void
    {
        [, $fileSystem] = $this->fileSystem();

        $this->assertSame([], $fileSystem->loadAttributes());
    }

    public function testStoreAttributesThenLoadAttributesRoundTrips(): void
    {
        [, $fileSystem] = $this->fileSystem();

        $fileSystem->storeAttributes([CustomersController::class]);
        $attributes = $fileSystem->loadAttributes();

        $this->assertArrayHasKey(CustomersController::class, $attributes);
    }

    public function testIsAvailableIsFalseWhenTheStoredMetaFileIsCorrupted(): void
    {
        [$options, $fileSystem] = $this->fileSystem();
        $this->router([CustomersController::class], [], $options);

        // Overwrite a validly-shaped cache with one whose meta.php doesn't
        // return an array at all, simulating a corrupted/hand-edited file.
        file_put_contents($this->tempDir . '/meta.php', "<?php\nreturn 'not-an-array';\n");

        $this->assertFalse($fileSystem->isAvailable([CustomersController::class]));
    }

    public function testIsAvailableSkipsNonexistentControllerClasses(): void
    {
        [$options, $fileSystem] = $this->fileSystem();
        $this->router([CustomersController::class], [], $options);

        $this->assertTrue($fileSystem->isAvailable([CustomersController::class, 'TotallyMadeUpClassName']));
    }

    public function testIsAvailableIsFalseForAClassWithNoBackingFile(): void
    {
        [$options, $fileSystem] = $this->fileSystem();
        $this->router([CustomersController::class], [], $options);

        // Internal PHP classes like stdClass have no source file at all;
        // isAvailable() must treat that as "not cacheable", not crash.
        $this->assertFalse($fileSystem->isAvailable([CustomersController::class, \stdClass::class]));
    }

    public function testStoreFromRouterSkipsNonexistentControllerClassesInMeta(): void
    {
        [$options] = $this->fileSystem();

        // Must not crash building routes.php/meta.php just because one of
        // the given "controllers" doesn't actually exist.
        $this->router([CustomersController::class, 'AnotherTotallyMadeUpClassName'], [], $options);

        $this->assertFileExists($this->tempDir . '/meta.php');
    }

    public function testLoadToRouterIsANoOpWhenNoRouteFileExistsYet(): void
    {
        [$options, $fileSystem] = $this->fileSystem();
        $router = $this->router([CustomersController::class], [], $options);
        $routesBefore = $router->getRoutes();

        // Simulate calling loadToRouter() directly (rather than through the
        // constructor, which only calls it after isAvailable() confirms the
        // file exists) when the route file is missing.
        unlink($this->tempDir . '/routes.php');
        $fileSystem->loadToRouter($router);

        $this->assertEquals($routesBefore, $router->getRoutes());
    }

    public function testHasCachedRoutesReflectsWhetherARouteFileExists(): void
    {
        [$options, $fileSystem] = $this->fileSystem();
        $this->assertFalse($fileSystem->hasCachedRoutes());

        $this->router([CustomersController::class], [], $options);

        $this->assertTrue($fileSystem->hasCachedRoutes());
    }

    public function testLoadCachedServicesReturnsNullBeforeAnythingIsStored(): void
    {
        [, $fileSystem] = $this->fileSystem();
        $this->assertNull($fileSystem->loadCachedServices());
    }

    public function testLoadCachedServicesRoundTripsTheServiceClassList(): void
    {
        [$options, $fileSystem] = $this->fileSystem();
        $services = [CustomersController::class, OrdersController::class];

        $this->router([CustomersController::class], $services, $options);

        $this->assertSame($services, $fileSystem->loadCachedServices());
    }

    public function testTrustModeSkipsValidationAndLoadsTheExistingCacheEvenIfStale(): void
    {
        [$options, $fileSystem] = $this->fileSystem();
        $this->router([CustomersController::class], [], $options);

        // Make the cache genuinely stale (isAvailable() would now say no)...
        $this->router([CustomersController::class, OrdersController::class], [], $options);
        $this->assertFalse($fileSystem->isAvailable([CustomersController::class]));

        // ...but in trust mode, a Router built for the *original* single
        // controller must still load the (now two-controller) cache as-is,
        // proving it never calls isAvailable() at all.
        $options->cacheValidate = false;
        $trusted = $this->router([CustomersController::class], [], $options);

        $this->assertNotNull($trusted->findRoute($trusted->getRoutes(), 'POST', '/orders'));
    }

    public function testTrustModeFallsBackToCompilingOnFirstBootWhenNoCacheExistsYet(): void
    {
        [$options] = $this->fileSystem(validate: false);

        $router = $this->router([CustomersController::class], [], $options);

        $this->assertNotEmpty($router->getRoutes());
        $this->assertFileExists($this->tempDir . '/routes.php');
    }

    public function testTrustModeReusesCompiledViewAssetsWithoutRescanningTheViewsDirectory(): void
    {
        $app = Waypoint::create();
        $app->configure(function (FileSystemOptions $fs) {
            $fs->cacheDirectory = $this->tempDir;
        });
        $app->configure(function (RendererOptions $opts) {
            $opts->directory = __DIR__ . '/../Fixtures/ViewAssets';
        });
        $fileSystemOptions = $app->getContainer()->get(FileSystemOptions::class);
        $app->attach([CustomersController::class]);

        $spec = require $this->tempDir . '/routes.php';
        $cssFilename = $spec['viewAssetsByName']['WithBoth']['css'];
        $this->assertNotNull($cssFilename);
        // routes.php itself only ever holds {filename => mime} -- the
        // actual CSS text lives as a real file under getAssetsDirectory().
        $this->assertSame('text/css; charset=utf-8', $spec['viewAssetFiles'][$cssFilename]);
        $this->assertSame(
            "[data-view=\"WithBoth\"] {\n    .with-both { color: red; }\n}\n",
            file_get_contents($this->tempDir . '/assets/' . $cssFilename)
        );

        // Trust mode: a fresh Router must report the exact same mapping by
        // reading it back from the cache, proving it never re-ran
        // ViewAssets::compile() -- the real proof is that this still works
        // even though the fixture directory itself no longer resolves the
        // way it did at compile time (RendererOptions isn't reconfigured on
        // this second Router at all).
        Waypoint::reset();
        $fileSystemOptions->cacheValidate = false;
        $trusted = $this->router([CustomersController::class], [], $fileSystemOptions);

        $this->assertSame($cssFilename, $trusted->exportPlans()['viewAssetsByName']['WithBoth']['css']);
        $this->assertSame('text/css; charset=utf-8', $trusted->exportPlans()['viewAssetFiles'][$cssFilename]);
        $this->assertSame(
            "[data-view=\"WithBoth\"] {\n    .with-both { color: red; }\n}\n",
            file_get_contents($this->tempDir . '/assets/' . $cssFilename)
        );
    }

    public function testTrustModeReusesACompiledLayoutsOwnAssetsWithoutRescanningTheViewsDirectory(): void
    {
        // LayoutWithAssets.php/.css/.js (see Tests/Fixtures/Views/) is a
        // layout template, not a view -- ViewAssets::compile() discovers
        // its sibling CSS/JS the exact same way it does for any view, so
        // it must be equally present in the cache and equally exempt from
        // re-scanning under trust mode.
        $app = Waypoint::create();
        $app->configure(function (FileSystemOptions $fs) {
            $fs->cacheDirectory = $this->tempDir;
        });
        $app->configure(function (RendererOptions $opts) {
            $opts->directory = __DIR__ . '/../Fixtures/Views';
        });
        $fileSystemOptions = $app->getContainer()->get(FileSystemOptions::class);
        $app->attach([CustomersController::class]);

        $spec = require $this->tempDir . '/routes.php';
        $cssFilename = $spec['viewAssetsByName']['LayoutWithAssets']['css'];
        $this->assertNotNull($cssFilename);
        $this->assertSame('text/css; charset=utf-8', $spec['viewAssetFiles'][$cssFilename]);
        $this->assertStringContainsString(
            '[data-view="LayoutWithAssets"]',
            file_get_contents($this->tempDir . '/assets/' . $cssFilename)
        );

        // Trust mode: a fresh Router must report the exact same layout
        // asset mapping read back from the cache, without ever re-running
        // ViewAssets::compile() -- proven the same way the sibling test
        // above proves it for a plain view: RendererOptions isn't
        // reconfigured on this second Router at all, yet the lookup still
        // resolves correctly.
        Waypoint::reset();
        $fileSystemOptions->cacheValidate = false;
        $trusted = $this->router([CustomersController::class], [], $fileSystemOptions);

        $this->assertSame($cssFilename, $trusted->getViewAssets('LayoutWithAssets')['css']);
    }

    public function testEditingAViewsCssInvalidatesTheCacheUnderValidatingMode(): void
    {
        $scratchDir = $this->tempDir . '/views';
        mkdir($scratchDir, 0777, true);
        file_put_contents("$scratchDir/ScratchView.php", '<?php // placeholder view file, unrelated to any real controller');
        file_put_contents("$scratchDir/ScratchView.css", '.v1 { color: red; }');

        $app = Waypoint::create();
        $app->configure(function (FileSystemOptions $fs) {
            $fs->cacheDirectory = $this->tempDir; // validating (default) mode
        });
        $app->configure(function (RendererOptions $opts) use ($scratchDir) {
            $opts->directory = $scratchDir;
        });
        $app->attach([CustomersController::class]);
        $firstHash = require $this->tempDir . '/routes.php';
        $firstFilename = $firstHash['viewAssetsByName']['ScratchView']['css'];

        // Edit the CSS -- content AND mtime both change.
        file_put_contents("$scratchDir/ScratchView.css", '.v2 { color: blue; }');
        touch("$scratchDir/ScratchView.css", time() + 5);

        Waypoint::reset();
        $app2 = Waypoint::create();
        $app2->configure(function (FileSystemOptions $fs) {
            $fs->cacheDirectory = $this->tempDir;
        });
        $app2->configure(function (RendererOptions $opts) use ($scratchDir) {
            $opts->directory = $scratchDir;
        });
        $app2->attach([CustomersController::class]);
        $secondData = require $this->tempDir . '/routes.php';
        $secondFilename = $secondData['viewAssetsByName']['ScratchView']['css'];

        $this->assertNotSame($firstFilename, $secondFilename, 'a changed CSS file must get a new cache-busted filename');
        $this->assertSame('text/css; charset=utf-8', $secondData['viewAssetFiles'][$secondFilename]);
        $this->assertSame(
            "[data-view=\"ScratchView\"] {\n    .v2 { color: blue; }\n}\n",
            file_get_contents($this->tempDir . '/assets/' . $secondFilename)
        );
    }

    public function testGetAssetsDirectoryIsASubdirectoryOfTheCacheDirectory(): void
    {
        [, $fileSystem] = $this->fileSystem();

        $this->assertSame($this->tempDir . '/assets', $fileSystem->getAssetsDirectory());
    }

    public function testStoreViewAssetFilesWritesEachFileAndReturnsAContentFreeMimeMap(): void
    {
        [, $fileSystem] = $this->fileSystem();

        $result = $fileSystem->storeViewAssetFiles([
            'abc123.css' => ['content' => '.x { color: red; }', 'mime' => 'text/css; charset=utf-8'],
            'def456.js' => ['content' => "console.log('hi');", 'mime' => 'application/javascript; charset=utf-8'],
        ]);

        $this->assertSame([
            'abc123.css' => 'text/css; charset=utf-8',
            'def456.js' => 'application/javascript; charset=utf-8',
        ], $result);

        $this->assertSame('.x { color: red; }', file_get_contents($fileSystem->getAssetsDirectory() . '/abc123.css'));
        $this->assertSame("console.log('hi');", file_get_contents($fileSystem->getAssetsDirectory() . '/def456.js'));
    }

    public function testStoreViewAssetFilesReturnsEmptyArrayForEmptyInputWithoutCreatingTheDirectory(): void
    {
        [, $fileSystem] = $this->fileSystem();

        $result = $fileSystem->storeViewAssetFiles([]);

        $this->assertSame([], $result);
        $this->assertDirectoryDoesNotExist($fileSystem->getAssetsDirectory());
    }

    public function testStoreViewAssetFilesDoesNotRewriteAnAlreadyExistingFile(): void
    {
        // Content-hashed filenames are immutable -- storeViewAssetFiles()
        // trusts an existing file as-is rather than rewriting it, proven
        // here directly by writing the same filename twice with different
        // content and confirming the first write wins.
        [, $fileSystem] = $this->fileSystem();

        $fileSystem->storeViewAssetFiles(['abc123.css' => ['content' => 'first', 'mime' => 'text/css; charset=utf-8']]);
        $fileSystem->storeViewAssetFiles(['abc123.css' => ['content' => 'second', 'mime' => 'text/css; charset=utf-8']]);

        $this->assertSame('first', file_get_contents($fileSystem->getAssetsDirectory() . '/abc123.css'));
    }

    public function testReadViewAssetFileReturnsStoredContent(): void
    {
        [, $fileSystem] = $this->fileSystem();
        $fileSystem->storeViewAssetFiles(['abc123.css' => ['content' => '.x { color: red; }', 'mime' => 'text/css; charset=utf-8']]);

        $this->assertSame('.x { color: red; }', $fileSystem->readViewAssetFile('abc123.css'));
    }

    public function testReadViewAssetFileReturnsNullWhenTheFileDoesNotExist(): void
    {
        [, $fileSystem] = $this->fileSystem();

        $this->assertNull($fileSystem->readViewAssetFile('never-stored.css'));
    }
}
