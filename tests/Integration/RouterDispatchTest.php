<?php

namespace Waypoint\Tests\Integration;

use Waypoint\Waypoint;
use Waypoint\Options\{CsrfOptions, RendererOptions, FileSystemOptions};
use Waypoint\Tests\Fixtures\Controllers\{CustomersController, OrdersController, ProductsController};

final class RouterDispatchTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $app = Waypoint::create();
        $app->configure(function (RendererOptions $opts) {
            $opts->directory = __DIR__ . '/../Fixtures/Views';
            $opts->layout = '_Layout.php';
        });
        $app->attach([
            CustomersController::class,
            OrdersController::class,
            ProductsController::class,
        ]);
    }

    public function testStaticRouteIsMatchedExactly(): void
    {
        $output = $this->dispatch('POST', '/orders');
        $this->assertSame('"pong"', $output);
    }

    public function testDynamicRouteBindsPathPlaceholderToParamAttribute(): void
    {
        $output = $this->dispatch('GET', '/customers/1/orders');
        $this->assertSame(['customerId' => '1', 'status' => null], json_decode($output, true));
    }

    public function testDynamicRouteAlsoBindsQueryAttribute(): void
    {
        $output = $this->dispatch('GET', '/customers/1/orders?status=open');
        $this->assertSame(['customerId' => '1', 'status' => 'open'], json_decode($output, true));
    }

    public function testAnotherDynamicSegmentPattern(): void
    {
        $output = $this->dispatch('GET', '/orders/42');
        $this->assertSame('"pong"', $output);
    }

    public function testUnknownRouteReturns404Json(): void
    {
        $output = $this->dispatch('GET', '/this/does/not/exist');
        $this->assertSame(['error' => 'Not found'], json_decode($output, true));
    }

    public function testBodyBoundDtoIsPopulatedFromRequestBody(): void
    {
        $output = $this->dispatch('POST', '/products', body: ['name' => 'Widget', 'sku' => 'AB-12']);

        $this->assertSame(['name' => 'Widget', 'sku' => 'AB-12'], json_decode($output, true));
    }

    public function testInvalidBodyIsRejectedWithValidationDetails(): void
    {
        $output = $this->dispatch('POST', '/products', body: ['name' => '', 'sku' => 'x']);
        $decoded = json_decode($output, true);

        $this->assertSame('Validation failed', $decoded['title']);
        $this->assertArrayHasKey('name', $decoded['errors']);
        $this->assertArrayHasKey('sku', $decoded['errors']);
    }

    public function testFileFormatterSetsDownloadHeadersAndRawBody(): void
    {
        $output = $this->dispatch('GET', '/products/report.txt');

        $this->assertSame("sku,name\nA-1,Widget\n", $output);
        $headers = $this->sentHeaders();
        // PHP's default_charset ini setting appends ";charset=UTF-8" to any
        // Content-Type header that doesn't already specify one -- not
        // something Waypoint itself adds, so we only check the prefix.
        $this->assertStringStartsWith('text/plain', $headers['content-type'] ?? '');
        $this->assertSame('attachment; filename="report.txt"', $headers['content-disposition'] ?? null);
    }

    public function testFileFormatterReadsAnActualFilePathFromDisk(): void
    {
        $output = $this->dispatch('GET', '/products/logo.svg');

        $this->assertSame("body { color: red; }\n", $output);
        $this->assertStringStartsWith('image/svg+xml', $this->sentHeaders()['content-type'] ?? '');
    }

    public function testXmlFormatterWrapsArrayInRootElement(): void
    {
        $output = $this->dispatch('GET', '/products/export.xml');

        $this->assertStringContainsString('<root>', $output);
        $this->assertStringContainsString('<name>Widget</name>', $output);
        $this->assertStringContainsString('<sku>A-1</sku>', $output);
    }

    public function testViewRouteRendersFullPageByDefault(): void
    {
        $output = $this->dispatch('GET', '/customers');

        $this->assertStringContainsString('<html', $output);
        $this->assertStringContainsString('testview hit', $output);
    }

    public function testViewRouteRendersPartialWhenWaypointAcceptHeaderRequestsIt(): void
    {
        $output = $this->dispatch('GET', '/customers', ['X-Waypoint-Accept' => 'partial']);

        $this->assertStringNotContainsString('<html', $output);
        $this->assertStringContainsString('testview hit', $output);
    }

    public function testViewWithSiblingCssAndJsEmitsAssetHeaders(): void
    {
        // HomePage.css/HomePage.js are real sibling fixture files -- see
        // Tests/Fixtures/Views/.
        $this->dispatch('GET', '/customers');
        $headers = $this->sentHeaders();

        $this->assertMatchesRegularExpression('/^[a-f0-9]{12}\.css$/', $headers['x-waypoint-view-css'] ?? '');
        $this->assertMatchesRegularExpression('/^[a-f0-9]{12}\.js$/', $headers['x-waypoint-view-js'] ?? '');
        $this->assertSame('HomePage', $headers['x-waypoint-view-name'] ?? null);
    }

    public function testViewAssetIsServedFromAssetsRouteWithLongLivedCacheHeaders(): void
    {
        $this->dispatch('GET', '/customers');
        $cssFilename = $this->sentHeaders()['x-waypoint-view-css'];

        $output = $this->dispatch('GET', "/assets/$cssFilename");
        $headers = $this->sentHeaders();

        // Scoped by ViewAssets::compile() -- wrapped in the view's own
        // [data-view="HomePage"] nesting parent, not served raw.
        $this->assertSame("[data-view=\"HomePage\"] {\n    .home-page {\n        color: navy;\n    }\n}\n", $output);
        $this->assertStringStartsWith('text/css', $headers['content-type'] ?? '');
        $this->assertSame('public, max-age=31536000, immutable', $headers['cache-control'] ?? null);
    }

    public function testJsViewAssetIsServedWithTheJavascriptMimeType(): void
    {
        $this->dispatch('GET', '/customers');
        $jsFilename = $this->sentHeaders()['x-waypoint-view-js'];

        $output = $this->dispatch('GET', "/assets/$jsFilename");
        $headers = $this->sentHeaders();

        $this->assertSame("console.log('HomePage view script loaded');\n", $output);
        $this->assertStringStartsWith('application/javascript', $headers['content-type'] ?? '');
    }

    public function testUnknownAssetFilenameFallsThroughToTheNormal404(): void
    {
        $output = $this->dispatch('GET', '/assets/does-not-exist.css');

        $this->assertSame(['error' => 'Not found'], json_decode($output, true));
    }

    public function testAKnownAssetFilenameMissingFromDiskFallsThroughToTheNormal404(): void
    {
        // The compiled {filename => mime} manifest says this filename is
        // legit, but the physical file under FileSystem::getAssetsDirectory()
        // was removed from under it (e.g. a partially cleared cache
        // directory) -- must 404 gracefully, not throw trying to read it.
        // A dedicated cache dir, not the shared default the rest of this
        // class's tests rely on -- deleting a file out of that would leak
        // into whichever other test happens to run next.
        $tempDir = sys_get_temp_dir() . '/waypoint-test-' . bin2hex(random_bytes(6));
        try {
            $app = Waypoint::create();
            $app->configure(function (FileSystemOptions $fs) use ($tempDir) {
                $fs->cacheDirectory = $tempDir;
            });
            $app->configure(function (RendererOptions $opts) {
                $opts->directory = __DIR__ . '/../Fixtures/Views';
                $opts->layout = '_Layout.php';
            });
            $app->attach([CustomersController::class]);

            $this->dispatch('GET', '/customers');
            $cssFilename = $this->sentHeaders()['x-waypoint-view-css'];
            unlink("$tempDir/assets/$cssFilename");

            $output = $this->dispatch('GET', "/assets/$cssFilename");

            $this->assertSame(['error' => 'Not found'], json_decode($output, true));
        } finally {
            $this->removeDirectory($tempDir);
        }
    }

    public function testViewsWaypointJsTagPointsAtTheCompiledBundle(): void
    {
        $output = $this->dispatch('GET', '/customers');

        $this->assertMatchesRegularExpression('#<script src="/assets/waypoint\.[0-9a-f]{12}\.js"></script>#', $output);
    }

    public function testWaypointJsIsServedFromTheAssetsRouteWithImmutableCaching(): void
    {
        $page = $this->dispatch('GET', '/customers');
        preg_match('#src="(/assets/waypoint\.[0-9a-f]{12}\.js)"#', $page, $m);

        $output = $this->dispatch('GET', $m[1]);

        $this->assertSame(file_get_contents(dirname((new \ReflectionClass(Waypoint::class))->getFileName()) . '/UI/waypoint.js'), $output);
        $this->assertStringStartsWith('application/javascript', $this->sentHeaders()['content-type'] ?? '');
        $this->assertSame('public, max-age=31536000, immutable', $this->sentHeaders()['cache-control'] ?? null);
    }

    public function testViewsWaypointJsTagCarriesNonDefaultCsrfNames(): void
    {
        $app = Waypoint::create();
        $app->configure(function (RendererOptions $opts) {
            $opts->directory = __DIR__ . '/../Fixtures/Views';
            $opts->layout = '_Layout.php';
        });
        $app->configure(function (CsrfOptions $opts) {
            $opts->cookieName = 'my_token';
        });
        $app->attach([CustomersController::class]);

        $output = $this->dispatch('GET', '/customers');

        // Only the value that differs from waypoint.js's own default is spelled out.
        $this->assertMatchesRegularExpression('#<script src="/assets/waypoint\.[0-9a-f]{12}\.js" data-csrf-cookie="my_token"></script>#', $output);
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

}
