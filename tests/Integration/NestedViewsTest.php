<?php

namespace Waypoint\Tests\Integration;

use Waypoint\Waypoint;
use Waypoint\Http\View;
use Waypoint\Options\{RendererOptions, FileSystemOptions};
use Waypoint\Tests\Fixtures\Controllers\NestedViewsController;

/**
 * Views in subdirectories of RendererOptions::$directory -- addressed as
 * `new View('Admin/Users')`, with their sibling .css/.js discovered,
 * scoped and served exactly like a top-level view's (see
 * ViewAssets::discoverViewNames()).
 */
final class NestedViewsTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $app = Waypoint::create();
        $app->configure(function (RendererOptions $opts) {
            $opts->directory = __DIR__ . '/../Fixtures/Views';
            $opts->layout = '_Layout.php';
        });
        $app->attach([NestedViewsController::class]);
    }

    public function testNestedViewRendersInsideTheRootLayout(): void
    {
        $output = $this->dispatch('GET', '/nested/admin-users');

        $this->assertStringContainsString('<html', $output);
        $this->assertStringContainsString('admin users view hit', $output);
    }

    public function testNestedViewsSiblingCssIsDiscoveredAndAnnouncedUnderItsSlashedName(): void
    {
        $this->dispatch('GET', '/nested/admin-users', ['X-Waypoint-Accept' => 'partial']);

        $headers = $this->sentHeaders();
        $this->assertSame('Admin/Users', $headers['x-waypoint-view-name'] ?? null);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{12}\.css$/', $headers['x-waypoint-view-css'] ?? '');
        $this->assertArrayNotHasKey('x-waypoint-view-js', $headers);
    }

    public function testNestedViewsCssIsScopedToItsSlashedNameAndServed(): void
    {
        $this->dispatch('GET', '/nested/admin-users', ['X-Waypoint-Accept' => 'partial']);
        $css = $this->sentHeaders()['x-waypoint-view-css'];

        $assetsPath = Waypoint::getConfig(FileSystemOptions::class)->assetsPath;
        $output = $this->dispatch('GET', "$assetsPath/$css");

        $this->assertStringContainsString('[data-view="Admin/Users"] {', $output);
        $this->assertStringContainsString('.users-table', $output);
    }

    public function testNestedViewsScopeAttributeCarriesTheSlashedName(): void
    {
        $view = new View('Admin/Users');
        $view->setAssets(['css' => 'abc.css', 'js' => null]);

        $this->assertSame('data-view="Admin/Users"', $view->scopeAttribute());
    }

    public function testAViewNameEscapingTheViewsDirectoryIsRejected(): void
    {
        $output = $this->dispatch('GET', '/nested/by-name?name=' . rawurlencode('../../../composer'));

        $this->assertSame(500, http_response_code());
        $this->assertStringNotContainsString('systonia', $output);
    }

    public function testAnAbsoluteViewNameIsRejected(): void
    {
        $this->dispatch('GET', '/nested/by-name?name=' . rawurlencode('/etc/passwd'));

        $this->assertSame(500, http_response_code());
    }

    /** @return array<string, string> */
    private function sentHeaders(): array
    {
        $raw = function_exists('xdebug_get_headers') ? xdebug_get_headers() : headers_list();
        $headers = [];
        foreach ($raw as $line) {
            [$name, $value] = array_map('trim', explode(':', $line, 2) + [1 => '']);
            $headers[strtolower($name)] = $value;
        }
        return $headers;
    }
}
