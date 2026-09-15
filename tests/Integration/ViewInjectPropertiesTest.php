<?php

namespace Waypoint\Tests\Integration;

use Waypoint\{Waypoint, App};
use Waypoint\Options\{RendererOptions, EnvironmentOptions};
use Waypoint\Tests\Fixtures\Controllers\ViewEnvController;

/**
 * View is a plain value object controller code `new`s up directly, never
 * through the container -- so #[Inject] on a View property (View::$env)
 * only works because Router::renderResult() runs a dedicated
 * injectViewProperties() pass on it right before render(). These tests
 * exercise that end to end, through a real dispatch().
 */
final class ViewInjectPropertiesTest extends IntegrationTestCase
{
    private function bootApp(): App
    {
        $app = Waypoint::create();
        $app->configure(function (RendererOptions $opts) {
            $opts->directory = __DIR__ . '/../Fixtures/Views';
        });
        $app->attach([ViewEnvController::class]);
        return $app;
    }

    public function testInjectedEnvironmentReflectsTheConfiguredEnvironmentOptions(): void
    {
        $app = $this->bootApp();
        $app->configure(function (EnvironmentOptions $opts) {
            $opts->set('APP_ENV', 'staging-under-test');
        });

        $output = $this->dispatch('GET', '/view-env');

        $this->assertSame('staging-under-test', trim($output));
    }

    public function testDefaultsToTheLazilyDetectedEnvironmentWhenNothingWasConfigured(): void
    {
        $app = $this->bootApp();

        $output = $this->dispatch('GET', '/view-env');

        // Nothing configured -- EnvironmentOptions lazily loads under the
        // CLI SAPI, which detects "development".
        $this->assertSame('development', trim($output));
    }

    public function testConfiguringEnvironmentOptionsAfterAttachStillReachesTheInjectedView(): void
    {
        // injectViewProperties() resolves EnvironmentOptions fresh from
        // the container on every render, so configure() order relative to
        // attach() doesn't matter.
        $app = $this->bootApp();
        $this->dispatch('GET', '/view-env'); // first render, nothing configured yet

        $app->configure(function (EnvironmentOptions $opts) {
            $opts->set('APP_ENV', 'configured-later');
        });
        $output = $this->dispatch('GET', '/view-env');

        $this->assertSame('configured-later', trim($output));
    }
}
