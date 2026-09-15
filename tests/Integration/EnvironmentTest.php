<?php

namespace Waypoint\Tests\Integration;

use Waypoint\{Waypoint, Environment};
use Waypoint\Options\EnvironmentOptions;
use Waypoint\Tests\Fixtures\Services\ServiceWithInjectedEnvironment;

/**
 * Environment is a stateless service: every call resolves the live
 * EnvironmentOptions the container currently holds (Waypoint::getConfig()),
 * rather than capturing one at construction time -- so, unlike the old
 * static Env, these tests need a real App/container (Waypoint::create()) to
 * configure EnvironmentOptions against. Pure load()/get()/set()/isDev()
 * behavior, which needs none of that, is covered directly by
 * EnvironmentOptionsTest instead.
 */
final class EnvironmentTest extends IntegrationTestCase
{
    private const FIXTURES_DIR = __DIR__ . '/../Fixtures/Env';

    public function testGetReflectsTheConfiguredEnvironmentOptions(): void
    {
        Waypoint::create()->configure(function (EnvironmentOptions $opts) {
            $opts->load(self::FIXTURES_DIR);
        });

        $this->assertSame('dev', (new Environment())->get('DYNAMIC'));
    }

    public function testIsDevReflectsTheConfiguredEnvironmentOptions(): void
    {
        Waypoint::create()->configure(function (EnvironmentOptions $opts) {
            $opts->set('APP_ENV', 'production');
        });

        $this->assertFalse((new Environment())->isDev());
    }

    public function testSetMutatesTheSameContainerHeldEnvironmentOptions(): void
    {
        $app = Waypoint::create();
        (new Environment())->set('SOME_KEY', 'some-value');

        $this->assertSame('some-value', $app->getContainer()->get(EnvironmentOptions::class)->get('SOME_KEY'));
    }

    public function testLoadPopulatesTheSameContainerHeldEnvironmentOptions(): void
    {
        $app = Waypoint::create();
        (new Environment())->load(self::FIXTURES_DIR);

        $this->assertSame('dev', $app->getContainer()->get(EnvironmentOptions::class)->get('DYNAMIC'));
    }

    public function testEnvironmentIsInjectableIntoAServiceAndReflectsTheConfiguredEnvironmentOptions(): void
    {
        $app = Waypoint::create();
        $app->configure(function (EnvironmentOptions $opts) {
            $opts->load(self::FIXTURES_DIR);
        });
        $app->attach([ServiceWithInjectedEnvironment::class]);

        $service = $app->getContainer()->get(ServiceWithInjectedEnvironment::class);

        $this->assertSame('dev', $service->getEnvironment()?->get('DYNAMIC'));
    }

    public function testConfiguringEnvironmentOptionsAfterAttachStillReachesAnAlreadyInjectedEnvironment(): void
    {
        // Environment never captures EnvironmentOptions at construction
        // time -- it resolves the live one on every call -- so configure()
        // order relative to attach()/injection doesn't matter.
        $app = Waypoint::create();
        $app->attach([ServiceWithInjectedEnvironment::class]);

        $app->configure(function (EnvironmentOptions $opts) {
            $opts->load(self::FIXTURES_DIR);
        });

        $service = $app->getContainer()->get(ServiceWithInjectedEnvironment::class);

        $this->assertSame('dev', $service->getEnvironment()?->get('DYNAMIC'));
    }
}
