<?php

namespace Waypoint\Tests\Unit\Http;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Waypoint\{Html, Waypoint};
use Waypoint\Http\View;
use Waypoint\Options\{FileSystemOptions, RendererOptions};

final class ViewTest extends TestCase
{
    protected function setUp(): void
    {
        Waypoint::reset();
    }

    protected function tearDown(): void
    {
        Waypoint::reset();
    }

    private function configureRenderer(?string $layout = null): void
    {
        $app = Waypoint::create();
        $app->configure(function (RendererOptions $opts) use ($layout) {
            $opts->directory = __DIR__ . '/../../Fixtures/Views';
            if ($layout !== null) {
                $opts->layout = $layout;
            }
        });
    }

    public function testSectionReturnsEmptyStringWhenUndefinedAndNotRequired(): void
    {
        $view = new View('HomePage');
        $this->assertSame('', (string) $view->section('missing'));
    }

    public function testSectionThrowsWhenRequiredAndUndefined(): void
    {
        $view = new View('HomePage');
        $this->expectException(RuntimeException::class);
        (string) $view->section('missing', required: true);
    }

    public function testEndSectionWithoutStartThrows(): void
    {
        $view = new View('HomePage');
        $this->expectException(RuntimeException::class);
        $view->endSection();
    }

    public function testStartingASectionTwiceThrows(): void
    {
        $view = new View('HomePage');
        $view->startSection('body-scripts');
        try {
            $this->expectException(RuntimeException::class);
            $view->startSection('body-scripts');
        } finally {
            // The first startSection() opened an output buffer that never
            // gets closed since we never reach a matching endSection().
            ob_end_clean();
        }
    }

    public function testAViewFromADirectoryOfItsOwnUsesItsOwnLayout(): void
    {
        $this->configureRenderer('_Layout.php');
        $view = new View('Page', 'x', directory: __DIR__ . '/../../Fixtures/PluginViews');

        $this->assertSame("<main><p>plugin x</p>\n</main>\n", $view->render());
    }

    public function testRenderThrowsWhenViewFileMissing(): void
    {
        $this->configureRenderer();
        $view = new View('DoesNotExist');
        $this->expectException(RuntimeException::class);
        $view->render();
    }

    public function testPartialRenderSkipsLayoutEvenWhenOneResolves(): void
    {
        $this->configureRenderer('_Layout.php');
        $view = new View('HomePage', null, partial: true);

        $output = $view->render();

        $this->assertStringContainsString('testview hit', $output);
        $this->assertStringNotContainsString('<html', $output);
    }

    public function testEchoEscapesAndOnlyHtmlOrPhpEchoPrintsMarkup(): void
    {
        $this->configureRenderer();
        $view = new View('Escaping', ['text' => '<b>"Ada" & co</b>', 'html' => new Html('<em>safe</em>'), 'raw' => '<hr>'], partial: true);

        $this->assertSame(
            "<p>&lt;b&gt;&quot;Ada&quot; &amp; co&lt;/b&gt;</p>\n<div><em>safe</em></div>\n<hr><i>Escaping.php Views</i>\n",
            $view->render()
        );
    }

    public function testEchoingAnArrayIsATypeError(): void
    {
        $this->configureRenderer();

        $this->expectException(\TypeError::class);
        (new View('Escaping', ['text' => ['no'], 'html' => '', 'raw' => ''], partial: true))->render();
    }

    public function testWithACacheDirectoryTheCompiledViewIsAFileThere(): void
    {
        $cache = sys_get_temp_dir() . '/waypoint-views-' . bin2hex(random_bytes(4));
        $this->configureRenderer();
        Waypoint::create()->configure(fn (FileSystemOptions $fs) => $fs->cacheDirectory = $cache);
        $view = new View('Escaping', ['text' => '<b>', 'html' => '', 'raw' => ''], partial: true);

        $this->assertStringStartsWith('<p>&lt;b&gt;</p>', $view->render());
        $compiled = glob("$cache/views/*.php") ?: [];
        $this->assertCount(1, $compiled);
        $this->assertStringContainsString('View::escape(', (string) file_get_contents($compiled[0]));

        array_map('unlink', $compiled);
        rmdir("$cache/views");
        rmdir($cache);
    }

    public function testFullRenderWrapsContentInLayoutAndFillsSections(): void
    {
        $this->configureRenderer('_Layout.php');
        $view = new View('HomePage');

        $output = $view->render();

        $this->assertStringContainsString('<html', $output);
        $this->assertStringContainsString('testview hit', $output);
        $this->assertStringContainsString("console.log('hello world!');", $output);
    }

    public function testPerInstanceLayoutOverridesTheConfiguredDefault(): void
    {
        // The constructor's own $layout argument (not RendererOptions'
        // default) takes priority when given explicitly.
        $this->configureRenderer('_Layout.php');
        $view = new View('HomePage', null, false, 'AltLayout.php');

        $output = $view->render();

        $this->assertStringContainsString('alt-layout', $output);
        $this->assertStringNotContainsString('Bakery 1', $output); // _Layout.php's own marker
    }

    public function testDefaultLayoutNameHasNoExtensionSoItFallsBackToUnwrappedContent(): void
    {
        // RendererOptions defaults `layout` to '_Layout' (no .php), which
        // never matches a real file on disk (View appends no extension for
        // the layout, unlike the view file itself), so render() falls
        // back to the bare view content instead of throwing.
        $this->configureRenderer();
        $view = new View('HomePage');

        $output = $view->render();

        $this->assertStringNotContainsString('<html', $output);
        $this->assertStringContainsString('testview hit', $output);
    }

    public function testAssetTagsIsEmptyByDefault(): void
    {
        $view = new View('HomePage');
        $this->assertSame('', (string) $view->assetTags());
    }

    public function testAssetTagsRendersLinkAndScriptWhenBothAreSet(): void
    {
        $view = new View('HomePage');
        $view->setAssets(['css' => 'abc123.css', 'js' => 'def456.js']);

        $tags = (string) $view->assetTags();

        // data-wp-asset must be present and match waypoint.js's own
        // client-side dedup marker (see assets.ts's injectOnce()). A
        // server-rendered tag missing it is invisible to that dedup check,
        // and the client injects a second, duplicate tag for the same
        // asset the next time this view's CSS/JS is needed.
        $this->assertStringContainsString(
            '<link rel="stylesheet" href="/assets/abc123.css">',
            $tags
        );
        $this->assertStringContainsString(
            '<script src="/assets/def456.js"></script>',
            $tags
        );
    }

    public function testAssetTagsOnlyRendersTheOneAssetThatIsSet(): void
    {
        $view = new View('HomePage');
        $view->setAssets(['css' => 'abc123.css', 'js' => null]);

        $tags = (string) $view->assetTags();

        $this->assertStringContainsString(
            '<link rel="stylesheet" href="/assets/abc123.css">',
            $tags
        );
        $this->assertStringNotContainsString('<script', $tags);
    }

    public function testScopeAttributeIsEmptyByDefault(): void
    {
        $view = new View('HomePage');
        $this->assertSame('', (string) $view->scopeAttribute());
    }

    public function testScopeAttributeReturnsDataViewWhenAnyAssetIsSet(): void
    {
        $view = new View('HomePage');
        $view->setAssets(['css' => null, 'js' => 'def456.js']);

        $this->assertSame('data-view="HomePage"', (string) $view->scopeAttribute());
    }

    public function testScopeAttributeEscapesTheViewName(): void
    {
        $view = new View('Weird"Name');
        $view->setAssets(['css' => 'abc123.css', 'js' => null]);

        $this->assertSame('data-view="Weird&quot;Name"', (string) $view->scopeAttribute());
    }

    public function testLayoutAssetTagsIsEmptyBeforeRender(): void
    {
        $view = new View('HomePage');
        $this->assertSame('', (string) $view->layoutAssetTags());
    }

    public function testLayoutScopeAttributeIsEmptyBeforeRender(): void
    {
        $view = new View('HomePage');
        $this->assertSame('', (string) $view->layoutScopeAttribute());
    }

    public function testRenderWithoutAnAppRouterLeavesLayoutAssetsEmpty(): void
    {
        // configureRenderer() only sets up RendererOptions; attach() is
        // never called, so App has no Router yet. render() must degrade to
        // "no layout assets" rather than throwing on the uninitialized
        // Router property (see App::hasRouter()).
        $this->configureRenderer('_Layout.php');
        $view = new View('HomePage');

        $view->render();

        $this->assertSame('', (string) $view->layoutAssetTags());
        $this->assertSame('', (string) $view->layoutScopeAttribute());
    }

    public function testSetAssetsPathChangesTheHrefSrcPrefixOnAssetTags(): void
    {
        $view = new View('HomePage');
        $view->setAssets(['css' => 'abc123.css', 'js' => 'def456.js']);
        $view->setAssetsPath('/static');

        $tags = (string) $view->assetTags();

        $this->assertStringContainsString(
            '<link rel="stylesheet" href="/static/abc123.css">',
            $tags
        );
        $this->assertStringContainsString(
            '<script src="/static/def456.js"></script>',
            $tags
        );
    }

    public function testWaypointJsTagIsEmptyByDefault(): void
    {
        // No Router involved (bypassed by constructing View directly) ever
        // called setWaypointJsPath(), so it must render nothing, not a tag
        // pointing at a route that may not even exist.
        $view = new View('HomePage');

        $this->assertSame('', (string) $view->waypointJsTag());
    }

    public function testWaypointJsTagUsesTheConfiguredPath(): void
    {
        $view = new View('HomePage');
        $view->setWaypointJsPath('/waypoint.js');

        $this->assertSame('<script src="/waypoint.js"></script>' . "\n", (string) $view->waypointJsTag());
    }

    public function testWaypointJsTagRendersEscapedDataAttributes(): void
    {
        $view = new View('HomePage');
        $view->setWaypointJsPath('/waypoint.js', ['data-csrf-header' => 'X-My-Token', 'data-csrf-cookie' => 'a"b']);

        $this->assertSame(
            '<script src="/waypoint.js" data-csrf-header="X-My-Token" data-csrf-cookie="a&quot;b"></script>' . "\n",
            (string) $view->waypointJsTag()
        );
    }

    public function testWaypointJsTagIsEmptyAgainWhenExplicitlyResetToNull(): void
    {
        $view = new View('HomePage');
        $view->setWaypointJsPath('/waypoint.js');
        $view->setWaypointJsPath(null);

        $this->assertSame('', (string) $view->waypointJsTag());
    }

    public function testLayoutAssetTagsAndScopeAttributeStayEmptyForAPartialRenderEvenWhenTheLayoutHasAssets(): void
    {
        // A partial render never includes the layout at all, so its assets
        // are never resolved even if LayoutWithAssets.css/.js exist.
        $this->configureRenderer();
        $view = new View('HomePage', null, partial: true, layout: 'LayoutWithAssets.php');

        $view->render();

        $this->assertSame('', (string) $view->layoutAssetTags());
        $this->assertSame('', (string) $view->layoutScopeAttribute());
    }

    public function testDirectivesRenderInAViewAndItsLayout(): void
    {
        $this->configureRenderer('_Directives.php');
        $view = new View('Directives', ['title' => 'A & B', 'items' => ['a', '<b>'], 'html' => '<hr>']);

        $this->assertSame(
            "<title>A &amp; B</title>\n<main><ul><li class=\"first\">a</li><li class=\"\">&lt;b&gt;</li></ul><hr></main>\n",
            $view->render()
        );
    }

    public function testAModelOfAnotherTypeThanModelSaysIsATypeErrorInDevelopment(): void
    {
        $this->configureRenderer();
        $this->assertSame("<p><b></p>\n", (new View('Typed', new Html('<b>'), partial: true))->render());

        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('@model (Waypoint\Html): the model is string');
        (new View('Typed', '<b>', partial: true))->render();
    }

    public function testAnErrorInAViewNamesTheViewAndItsLine(): void
    {
        $this->configureRenderer();
        $file = realpath(__DIR__ . '/../../Fixtures/Views') . '/Failing.php';
        try {
            (new View('Failing', [], partial: true))->render();
            $this->fail('No error');
        } catch (\Error $e) {
            [$origin, $line] = View::origin($e) ?? ['', 0];
            $this->assertSame([$file, 2], [realpath($origin), $line]);
        }
    }

    public function testAViewWithAnErrorInItsSyntaxDoesNotRender(): void
    {
        $this->configureRenderer();
        try {
            (new View('Unclosed', true, partial: true))->render();
            $this->fail('No error');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringEndsWith("Unclosed.php', line 2: @if has no @endif", $e->getMessage());
            $this->assertSame(2, View::origin($e)[1] ?? null);
        }
    }
}
