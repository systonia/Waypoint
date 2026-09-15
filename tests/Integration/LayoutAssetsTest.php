<?php

namespace Waypoint\Tests\Integration;

use Waypoint\Waypoint;
use Waypoint\Options\RendererOptions;
use Waypoint\Tests\Fixtures\Controllers\LayoutAssetsController;

/**
 * ViewAssets::compile() discovers a sibling .css/.js for *every* '*.php'
 * file in the views directory, views and layouts alike -- these prove that
 * extends all the way through to the layout template itself getting its
 * own <link>/<script> tags and [data-view="..."] scope, no matter what the
 * layout is actually named (LayoutWithAssets.php here, not the app-wide
 * default _Layout).
 */
final class LayoutAssetsTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $app = Waypoint::create();
        $app->configure(function (RendererOptions $opts) {
            $opts->directory = __DIR__ . '/../Fixtures/Views';
        });
        $app->attach([LayoutAssetsController::class]);
    }

    public function testLayoutsOwnCssAndJsAreEmittedAsAssetTags(): void
    {
        $output = $this->dispatch('GET', '/layout-assets');

        $this->assertMatchesRegularExpression(
            '#<link rel="stylesheet" href="/assets/[a-f0-9]{12}\.css" data-wp-asset="[a-f0-9]{12}\.css">#',
            $output
        );
        $this->assertMatchesRegularExpression(
            '#<script src="/assets/[a-f0-9]{12}\.js" data-wp-asset="[a-f0-9]{12}\.js"></script>#',
            $output
        );
    }

    public function testLayoutsOwnAssetTagsAreDistinctFromTheViewsOwn(): void
    {
        $output = $this->dispatch('GET', '/layout-assets');

        // HomePage.css/HomePage.js (the view) and LayoutWithAssets.css/.js
        // (the layout) are different content, so they must hash to
        // different filenames -- both sets of tags present, not just one.
        $this->assertSame(2, substr_count($output, '<link rel="stylesheet"'));
        $this->assertSame(2, substr_count($output, '<script src='));
    }

    public function testLayoutScopeAttributeUsesTheLayoutsOwnBasename(): void
    {
        $output = $this->dispatch('GET', '/layout-assets');

        $this->assertStringContainsString('<body data-view="LayoutWithAssets">', $output);
    }

    public function testViewScopeAttributeStillUsesTheViewsOwnName(): void
    {
        $output = $this->dispatch('GET', '/layout-assets');

        $this->assertStringContainsString('data-view="HomePage"', $output);
    }

    public function testLayoutCssIsServedThroughTheAssetsRouteScopedToItsOwnName(): void
    {
        $output = $this->dispatch('GET', '/layout-assets');
        preg_match_all('#/assets/([a-f0-9]{12}\.css)#', $output, $matches);
        $cssFilenames = array_unique($matches[1]);
        $this->assertCount(2, $cssFilenames, 'view CSS and layout CSS must be two distinct compiled files');

        $sawLayoutScope = false;
        foreach ($cssFilenames as $filename) {
            $body = $this->dispatch('GET', "/assets/$filename");
            if (str_contains($body, '[data-view="LayoutWithAssets"]')) {
                $sawLayoutScope = true;
                $this->assertSame(
                    "[data-view=\"LayoutWithAssets\"] {\n    .layout-chrome {\n        color: crimson;\n    }\n}\n",
                    $body
                );
            }
        }
        $this->assertTrue($sawLayoutScope, 'expected one of the served CSS files to be the layout, scoped to its own name');
    }
}
