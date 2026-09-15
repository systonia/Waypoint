<?php

namespace Waypoint\Tests\Unit\Options;

use PHPUnit\Framework\TestCase;
use Waypoint\Options\EnvironmentOptions;

final class EnvironmentOptionsTest extends TestCase
{
    private const FIXTURES_DIR = __DIR__ . '/../../Fixtures/Env';

    public function testLoadMergesBaseAndPerEnvironmentFiles(): void
    {
        $opts = (new EnvironmentOptions())->load(self::FIXTURES_DIR);

        // .env.development overrides .env's DYNAMIC=base with DYNAMIC=dev,
        // and adds OTHER=other; APP_ENV defaults to "development" under the
        // CLI SAPI when it isn't set anywhere.
        $this->assertSame('development', $opts->get('APP_ENV'));
        $this->assertSame('dev', $opts->get('DYNAMIC'));
        $this->assertSame('other', $opts->get('OTHER'));
    }

    public function testLoadSkipsCommentsAndLinesWithoutAnEquals(): void
    {
        // Fixtures/Env/.env.development contains a leading "# ..." comment
        // and a bare "INVALID LINE"; load() must not choke on either and
        // must still parse the rest.
        $opts = (new EnvironmentOptions())->load(self::FIXTURES_DIR);

        $this->assertSame('other', $opts->get('OTHER'));
        $this->assertNull($opts->get('#'));
        $this->assertNull($opts->get('# this line is a comment and must be skipped'));
    }

    public function testLoadOverlaysDollarEnvSuperglobal(): void
    {
        $_ENV['TEST_FROM_DOLLAR_ENV'] = 'from-dollar-env';
        try {
            $opts = (new EnvironmentOptions())->load(self::FIXTURES_DIR);
            $this->assertSame('from-dollar-env', $opts->get('TEST_FROM_DOLLAR_ENV'));
        } finally {
            unset($_ENV['TEST_FROM_DOLLAR_ENV']);
        }
    }

    public function testGetReturnsDefaultForMissingKey(): void
    {
        $opts = (new EnvironmentOptions())->load(self::FIXTURES_DIR);

        $this->assertNull($opts->get('DOES_NOT_EXIST'));
        $this->assertSame('fallback', $opts->get('DOES_NOT_EXIST', 'fallback'));
    }

    public function testGetAutoLoadsWhenNothingHasBeenLoadedYet(): void
    {
        $opts = new EnvironmentOptions();

        // No explicit load() call: get() must trigger one lazily.
        $this->assertNotNull($opts->get('APP_ENV'));
    }

    public function testSetOverridesLoadedValues(): void
    {
        $opts = (new EnvironmentOptions())->load(self::FIXTURES_DIR);
        $opts->set('DYNAMIC', 'overridden');

        $this->assertSame('overridden', $opts->get('DYNAMIC'));
    }

    public function testIsDevReflectsAppEnv(): void
    {
        $opts = (new EnvironmentOptions())->load(self::FIXTURES_DIR);
        $this->assertTrue($opts->isDev());

        $opts->set('APP_ENV', 'production');
        $this->assertFalse($opts->isDev());
    }

    public function testIsDevAutoLoadsWhenNothingHasBeenLoadedYet(): void
    {
        $opts = new EnvironmentOptions();

        // Same lazy-load contract as get(): under the CLI SAPI,
        // detectEnvironment() defaults APP_ENV to "development", so this
        // can only be true if isDev() triggered a load itself.
        $this->assertTrue($opts->isDev());
    }

    public function testLoadWithoutAnExplicitDirDefaultsToTheCurrentWorkingDirectory(): void
    {
        // Regression test: load()'s default used to be __DIR__, which
        // resolves inside the framework's own src/Waypoint/Options under a
        // real Composer install (vendor/systonia/waypoint/src/Waypoint/Options)
        // -- never where an application's .env actually lives. It must
        // default to cwd instead, so chdir()-ing into the fixtures dir is
        // enough to pick up its .env/.env.development without passing a
        // path at all.
        $originalCwd = getcwd();
        chdir(self::FIXTURES_DIR);
        try {
            $opts = (new EnvironmentOptions())->load();
            $this->assertSame('dev', $opts->get('DYNAMIC'));
            $this->assertSame('other', $opts->get('OTHER'));
        } finally {
            chdir($originalCwd);
        }
    }

    public function testLoadReturnsSelfForChaining(): void
    {
        $opts = new EnvironmentOptions();
        $this->assertSame($opts, $opts->load(self::FIXTURES_DIR));
    }

    public function testSetReturnsSelfForChaining(): void
    {
        $opts = new EnvironmentOptions();
        $this->assertSame($opts, $opts->set('KEY', 'value'));
    }

    public function testAFreshInstanceDoesNotCarryOverAnotherInstancesState(): void
    {
        $first = (new EnvironmentOptions())->load(self::FIXTURES_DIR);
        $this->assertSame('dev', $first->get('DYNAMIC'));

        // A brand new instance must not see $first's loaded/overridden
        // state -- each EnvironmentOptions is its own independent store,
        // unlike the old static Env's single process-wide array.
        $second = new EnvironmentOptions();
        $this->assertNotSame('dev', $second->get('DYNAMIC'));
    }

    public function testLoadOverlaysBaseConfigJson(): void
    {
        $opts = (new EnvironmentOptions())->load(self::FIXTURES_DIR);
        $this->assertSame('base', $opts->get('FROM_CONFIG_JSON'));
    }

    public function testLoadOverlaysPerEnvironmentConfigJson(): void
    {
        $opts = (new EnvironmentOptions())->load(self::FIXTURES_DIR);
        $this->assertSame('dev', $opts->get('FROM_CONFIG_ENV_JSON'));
    }

    public function testLoadOverlaysTheLocalConfigFileByDefault(): void
    {
        $opts = (new EnvironmentOptions())->load(self::FIXTURES_DIR);
        $this->assertSame('local', $opts->get('FROM_CONFIG_LOCAL_JSON'));
    }

    public function testJsonConfigLayersOverrideEachOtherInOrder(): void
    {
        $opts = (new EnvironmentOptions())->load(self::FIXTURES_DIR);

        // config.json -> config.development.json -> config.local.json,
        // each overlaid on top of the last -- config.local.json must win.
        $this->assertSame('config.local.json', $opts->get('PRECEDENCE'));
    }

    public function testJsonConfigPreservesNonStringTypes(): void
    {
        $opts = (new EnvironmentOptions())->load(self::FIXTURES_DIR);

        $this->assertSame(true, $opts->get('JSON_BOOL'));
        $this->assertSame(42, $opts->get('JSON_INT'));
    }

    public function testLocalConfigFileDefaultsToConfigLocalJson(): void
    {
        $this->assertSame('config.local.json', (new EnvironmentOptions())->localConfigFile);
    }

    public function testLocalConfigFileCanBeDisabled(): void
    {
        $opts = new EnvironmentOptions();
        $opts->localConfigFile = null;
        $opts->load(self::FIXTURES_DIR);

        $this->assertNull($opts->get('FROM_CONFIG_LOCAL_JSON'));
        // The other JSON layers still apply -- only the local one is off.
        $this->assertSame('base', $opts->get('FROM_CONFIG_JSON'));
    }

    public function testLocalConfigFileNameCanBeCustomized(): void
    {
        $opts = new EnvironmentOptions();
        $opts->localConfigFile = 'config.custom.json';
        $opts->load(self::FIXTURES_DIR);

        $this->assertSame('custom-value', $opts->get('FROM_CUSTOM_LOCAL_FILE'));
        // The default config.local.json must NOT also be read once a
        // custom name is set.
        $this->assertNull($opts->get('FROM_CONFIG_LOCAL_JSON'));
    }

    public function testMissingJsonConfigFilesAreSilentlySkipped(): void
    {
        // Tests/Unit/Options itself has no config*.json at all -- must not throw.
        $opts = (new EnvironmentOptions())->load(__DIR__);
        $this->assertNull($opts->get('FROM_CONFIG_JSON'));
    }

    public function testMalformedJsonConfigFileIsSilentlySkipped(): void
    {
        $opts = (new EnvironmentOptions())->load(self::FIXTURES_DIR . '/Malformed');
        $this->assertNull($opts->get('ANYTHING'));
    }
}
