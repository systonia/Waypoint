<?php

namespace Waypoint\Tests\Integration;

use InvalidArgumentException;
use RuntimeException;
use Waypoint\OpenAPI\OpenAPIGenerator;
use Waypoint\Waypoint;
use Waypoint\Options\{FileSystemOptions, RendererOptions};
use Waypoint\Tests\Fixtures\Controllers\CustomersController;
use Waypoint\Tests\Fixtures\Plugins\{EchoPlugin, NeedsEchoPlugin};
use Waypoint\Tests\Fixtures\Support\CallTracker;

final class PluginTest extends IntegrationTestCase
{
    private EchoPlugin $plugin;

    private function boot(?string $cacheDir = null): void
    {
        $this->plugin = new EchoPlugin();
        $app = Waypoint::create();
        $app->plugin($this->plugin);
        $app->configure(function (RendererOptions $opts) {
            $opts->directory = __DIR__ . '/../Fixtures/Views';
        });
        if ($cacheDir !== null) {
            $app->configure(function (FileSystemOptions $fs) use ($cacheDir) {
                $fs->cacheDirectory = $cacheDir;
            });
        }
        $app->attach([CustomersController::class]);
    }

    public function testPluginIsBootedAndItsClassesAndMiddlewaresAreAttached(): void
    {
        $this->boot();

        $output = $this->dispatch('GET', '/echo/plain');

        $this->assertTrue($this->plugin->booted);
        $this->assertSame(['ok' => true], json_decode($output, true));
        $this->assertSame('yes', $this->sentHeaders()['x-echo-middleware'] ?? null);
    }

    public function testEndpointAnswersBeforeRouting(): void
    {
        $this->boot();

        $this->assertSame('pong GET', $this->dispatch('GET', '/echo/ping'));
    }

    public function testCompiledAttributeDataReachesGuardAndResponseHook(): void
    {
        $this->boot();

        $this->dispatch('GET', '/echo/tagged');
        $this->assertSame(['echo.guard:blue'], CallTracker::$calls);
        $this->assertSame('blue', $this->sentHeaders()['x-echo-tag'] ?? null);

        CallTracker::reset();
        $this->dispatch('GET', '/echo/plain');
        $this->assertSame(['echo.guard:-'], CallTracker::$calls);
        $this->assertSame('none', $this->sentHeaders()['x-echo-tag'] ?? null);
    }

    public function testGuardCanStopTheRequest(): void
    {
        $this->boot();

        $output = $this->dispatch('GET', '/echo/forbidden');

        $this->assertSame(403, $this->lastResponse?->status());
        $this->assertSame('tagged forbidden', json_decode($output, true)['detail']);
    }

    public function testArgumentBinderResolvesItsOwnParameterAttribute(): void
    {
        $this->boot();

        $this->assertSame(['word' => 'HI'], json_decode($this->dispatch('GET', '/echo/shout?word=hi'), true));
    }

    public function testRendererHandlesItsOwnReturnType(): void
    {
        $this->boot();

        $this->assertSame('rendered by plugin', $this->dispatch('GET', '/echo/result'));
        $this->assertStringStartsWith('text/echo', $this->sentHeaders()['content-type'] ?? '');
    }

    public function testOpenApiExtensionDocumentsThePluginsParametersAndReturnTypes(): void
    {
        $this->boot();
        $paths = (new OpenAPIGenerator(Waypoint::getRouter()))->generate()['paths'];

        $this->assertSame(
            [['name' => 'word', 'in' => 'query', 'required' => true, 'description' => 'Shouted back in upper case', 'schema' => ['type' => 'string']]],
            $paths['/echo/shout']['get']['parameters']
        );
        $this->assertSame(['text/echo' => ['schema' => ['type' => 'string']]], $paths['/echo/result']['get']['responses']['200']['content']);
        // A route the plugin doesn't know keeps the core's description.
        $this->assertSame(['application/json' => ['schema' => ['type' => 'object']]], $paths['/echo/plain']['get']['responses']['200']['content']);
    }

    public function testViewHelpersAndPluginAssetsAreAvailableInTemplates(): void
    {
        $this->boot();

        $output = $this->dispatch('GET', '/echo/view');

        $this->assertStringContainsString('<p>HELLO</p>', $output);
        $this->assertMatchesRegularExpression('#<link rel="stylesheet" href="/assets/echo\.echo\.[0-9a-f]{12}\.css">#', $output);
        preg_match('#href="(/assets/echo\.echo\.[0-9a-f]{12}\.css)"#', $output, $m);
        $this->assertSame(".echo { color: red; }\n", $this->dispatch('GET', $m[1]));
    }

    public function testUnknownViewHelperIsAClearError(): void
    {
        $this->boot();

        $output = $this->dispatch('GET', '/echo/view');
        $this->assertStringContainsString('HELLO', $output);

        $view = new \Waypoint\Http\View('PluginView');
        $this->expectException(\BadMethodCallException::class);
        $view->nope();
    }

    public function testRequiresAndNameAndHelperCollisionsAreBootErrors(): void
    {
        $app = Waypoint::create();

        try {
            $app->plugin(new NeedsEchoPlugin());
            $this->fail('missing requirement not detected');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('requires', $e->getMessage());
        }

        $app->plugin(new EchoPlugin());
        try {
            $app->plugin(new EchoPlugin());
            $this->fail('duplicate not detected');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('already registered', $e->getMessage());
        }

        $app->plugin(new NeedsEchoPlugin(clashingHelper: true));
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("View helper 'shout'");
        $app->attach([]);
    }

    public function testPluginMustBeRegisteredBeforeAttach(): void
    {
        $app = Waypoint::create();
        $app->attach([]);

        $this->expectException(InvalidArgumentException::class);
        $app->plugin(new EchoPlugin());
    }

    public function testCacheInputsInvalidateTheRouteCache(): void
    {
        $tempDir = sys_get_temp_dir() . '/waypoint-plugin-' . bin2hex(random_bytes(6));
        try {
            $this->boot($tempDir);
            $meta = require "$tempDir/meta.php";
            $this->assertArrayHasKey(realpath(__DIR__ . '/../Fixtures/Plugins/EchoPlugin.php'), $meta);
            $this->assertArrayHasKey(realpath(__DIR__ . '/../Fixtures/Plugins/echo.css'), $meta);
        } finally {
            foreach (glob("$tempDir/assets/*") ?: [] as $f) {
                unlink($f);
            }
            @rmdir("$tempDir/assets");
            foreach (glob("$tempDir/*") ?: [] as $f) {
                unlink($f);
            }
            @rmdir($tempDir);
        }
    }


    public function testAddingAPluginInvalidatesTheRouteCache(): void
    {
        $tempDir = sys_get_temp_dir() . '/waypoint-plugin-' . bin2hex(random_bytes(6));
        $attach = function (array $plugins) use ($tempDir): void {
            Waypoint::reset();
            $app = Waypoint::create();
            foreach ($plugins as $plugin) {
                $app->plugin($plugin);
            }
            $app->configure(function (FileSystemOptions $fs) use ($tempDir) {
                $fs->cacheDirectory = $tempDir;
            });
            $app->attach([CustomersController::class]);
        };
        try {
            $attach([]);
            $attach([new \Waypoint\Tests\Fixtures\Plugins\FallbackPlugin()]);
            $meta = require "$tempDir/meta.php";
            $this->assertArrayHasKey(realpath(__DIR__ . '/../Fixtures/Plugins/FallbackPlugin.php'), $meta);
        } finally {
            foreach (glob("$tempDir/{,assets/}*", GLOB_BRACE) ?: [] as $f) {
                is_file($f) && unlink($f);
            }
            @rmdir("$tempDir/assets");
            @rmdir($tempDir);
        }
    }

    public function testBootGetsWhatItsParametersAskFor(): void
    {
        $app = Waypoint::create();
        $plugin = new \Waypoint\Tests\Fixtures\Plugins\InjectedBootPlugin();
        $app->plugin($plugin);
        $app->attach([]);

        [$container, $cors, $env] = $plugin->got;
        $this->assertSame($app->getContainer(), $container);
        $this->assertSame($app->get(\Waypoint\Options\CorsOptions::class), $cors);
        $this->assertSame($app->get(\Waypoint\Environment::class), $env);
    }

    public function testAFallbackAnswersWhatNoRouteMatched(): void
    {
        $app = Waypoint::create();
        $app->plugin(new \Waypoint\Tests\Fixtures\Plugins\FallbackPlugin());
        $app->attach([\Waypoint\Tests\Fixtures\Controllers\PagesRouteController::class]);

        $this->assertSame('page /pages/about', $this->dispatch('GET', '/pages/about'));
        $this->assertSame('yes', $this->lastResponse?->header('X-Hooked'));
        // The app's own route comes first; anything else stays a 404
        $this->assertSame(['route' => true], json_decode($this->dispatch('GET', '/pages/own'), true));
        $this->dispatch('GET', '/nowhere');
        $this->assertSame(404, $this->lastResponse?->status());
    }
}
