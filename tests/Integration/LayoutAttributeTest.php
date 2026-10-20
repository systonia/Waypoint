<?php

namespace Waypoint\Tests\Integration;

use Waypoint\Options\RendererOptions;
use Waypoint\Tests\Fixtures\Controllers\LayoutAttributeController;
use Waypoint\Waypoint;

/**
 * #[Layout] on a controller, its parent or a method, and how it ranks: the View's own layout, then
 * the method's #[Layout], then the class's, then RendererOptions::$layout.
 */
final class LayoutAttributeTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $app = Waypoint::create();
        $app->configure(function (RendererOptions $options) {
            $options->directory = __DIR__ . '/../Fixtures/Views';
            $options->layout = '_Layout.php';
        });
        $app->attach([LayoutAttributeController::class]);
    }

    public function testTheParentClassLayoutReachesEveryRoute(): void
    {
        $output = $this->dispatch('GET', '/layout-attribute/inherited');

        $this->assertStringContainsString('<body class="alt-layout">', $output);
    }

    public function testTheMethodLayoutWinsOverTheClass(): void
    {
        $output = $this->dispatch('GET', '/layout-attribute/method');

        $this->assertStringContainsString('<title>Bakery 1</title>', $output);
        $this->assertStringNotContainsString('alt-layout', $output);
    }

    public function testTheLayoutIsTheOneOfTheRouteTheRequestReached(): void
    {
        // delegated() returns the View of inherited(), a route that would render in AltLayout.php
        $output = $this->dispatch('GET', '/layout-attribute/delegated');

        $this->assertStringContainsString('<title>Bakery 1</title>', $output);
        $this->assertStringNotContainsString('alt-layout', $output);
    }

    public function testLayoutNullRendersWithoutLayout(): void
    {
        $output = $this->dispatch('GET', '/layout-attribute/none');

        $this->assertStringContainsString('testview hit', $output);
        $this->assertStringNotContainsString('<html', $output);
    }

    public function testTheViewsOwnLayoutWinsOverTheAttribute(): void
    {
        $output = $this->dispatch('GET', '/layout-attribute/explicit');

        $this->assertStringContainsString('<body class="alt-layout">', $output);
    }
}
