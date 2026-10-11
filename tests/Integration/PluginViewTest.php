<?php

namespace Waypoint\Tests\Integration;

use Waypoint\Options\RendererOptions;
use Waypoint\Tests\Fixtures\Controllers\PluginViewController;
use Waypoint\Waypoint;

/** A view from a directory of its own (a plugin's) shares its name with an app view that has CSS/JS. */
final class PluginViewTest extends IntegrationTestCase
{
    public function testItRendersFromItsDirectoryWithoutTheAppViewsAssets(): void
    {
        $app = Waypoint::create();
        $app->configure(function (RendererOptions $opts) {
            $opts->directory = __DIR__ . '/../Fixtures/Views';
        });
        $app->attach([PluginViewController::class]);

        $output = $this->dispatch('GET', '/plugin-view');

        $this->assertSame("<main><p>plugin home</p>\n</main>\n", $output);
    }
}
