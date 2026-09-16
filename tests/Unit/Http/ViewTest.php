<?php

namespace Waypoint\Tests\Unit\Http;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Waypoint\{Waypoint};
use Waypoint\Http\View;
use Waypoint\Options\RendererOptions;

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
        $this->assertSame('', $view->section('missing'));
    }

    public function testSectionThrowsWhenRequiredAndUndefined(): void
    {
        $view = new View('HomePage');
        $this->expectException(RuntimeException::class);
        $view->section('missing', required: true);
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
        // the layout, unlike the view file itself) -- so render() falls
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
        $this->assertSame('', $view->assetTags());
    }

    public function testAssetTagsRendersLinkAndScriptWhenBothAreSet(): void
    {
        $view = new View('HomePage');
        $view->setAssets(['css' => 'abc123.css', 'js' => 'def456.js']);

        $tags = $view->assetTags();

        // data-wp-asset must be present and match waypoint.js's own
        // client-side dedup marker (see assets.ts's injectOnce()) -- a
        // server-rendered tag missing it is invisible to that dedup check,
        // and the client injects a second, duplicate tag for the same
        // asset the next time this view's CSS/JS is needed.
        $this->assertStringContainsString(
            '<link rel="stylesheet" href="/assets/abc123.css" data-wp-asset="abc123.css">',
            $tags
        );
        $this->assertStringContainsString(
            '<script src="/assets/def456.js" data-wp-asset="def456.js"></script>',
            $tags
        );
    }

    public function testAssetTagsOnlyRendersTheOneAssetThatIsSet(): void
    {
        $view = new View('HomePage');
        $view->setAssets(['css' => 'abc123.css', 'js' => null]);

        $tags = $view->assetTags();

        $this->assertStringContainsString(
            '<link rel="stylesheet" href="/assets/abc123.css" data-wp-asset="abc123.css">',
            $tags
        );
        $this->assertStringNotContainsString('<script', $tags);
    }

    public function testScopeAttributeIsEmptyByDefault(): void
    {
        $view = new View('HomePage');
        $this->assertSame('', $view->scopeAttribute());
    }

    public function testScopeAttributeReturnsDataViewWhenAnyAssetIsSet(): void
    {
        $view = new View('HomePage');
        $view->setAssets(['css' => null, 'js' => 'def456.js']);

        $this->assertSame('data-view="HomePage"', $view->scopeAttribute());
    }

    public function testScopeAttributeEscapesTheViewName(): void
    {
        $view = new View('Weird"Name');
        $view->setAssets(['css' => 'abc123.css', 'js' => null]);

        $this->assertSame('data-view="Weird&quot;Name"', $view->scopeAttribute());
    }

    public function testLayoutAssetTagsIsEmptyBeforeRender(): void
    {
        $view = new View('HomePage');
        $this->assertSame('', $view->layoutAssetTags());
    }

    public function testLayoutScopeAttributeIsEmptyBeforeRender(): void
    {
        $view = new View('HomePage');
        $this->assertSame('', $view->layoutScopeAttribute());
    }

    public function testRenderWithoutAnAppRouterLeavesLayoutAssetsEmpty(): void
    {
        // configureRenderer() only sets up RendererOptions -- attach() is
        // never called, so App has no Router yet. render() must degrade to
        // "no layout assets" rather than throwing on the uninitialized
        // Router property (see App::hasRouter()).
        $this->configureRenderer('_Layout.php');
        $view = new View('HomePage');

        $view->render();

        $this->assertSame('', $view->layoutAssetTags());
        $this->assertSame('', $view->layoutScopeAttribute());
    }

    public function testSetAssetsPathChangesTheHrefSrcPrefixOnAssetTags(): void
    {
        $view = new View('HomePage');
        $view->setAssets(['css' => 'abc123.css', 'js' => 'def456.js']);
        $view->setAssetsPath('/static');

        $tags = $view->assetTags();

        $this->assertStringContainsString(
            '<link rel="stylesheet" href="/static/abc123.css" data-wp-asset="abc123.css">',
            $tags
        );
        $this->assertStringContainsString(
            '<script src="/static/def456.js" data-wp-asset="def456.js"></script>',
            $tags
        );
    }

    public function testWaypointJsTagIsEmptyByDefault(): void
    {
        // No Router involved (bypassed by constructing View directly) ever
        // called setWaypointJsPath() -- must render nothing, not a tag
        // pointing at a route that may not even exist.
        $view = new View('HomePage');

        $this->assertSame('', $view->waypointJsTag());
    }

    public function testWaypointJsTagUsesTheConfiguredPath(): void
    {
        $view = new View('HomePage');
        $view->setWaypointJsPath('/waypoint.js');

        $this->assertSame('<script src="/waypoint.js"></script>' . "\n", $view->waypointJsTag());
    }

    public function testWaypointJsTagRendersEscapedDataAttributes(): void
    {
        $view = new View('HomePage');
        $view->setWaypointJsPath('/waypoint.js', ['data-csrf-header' => 'X-My-Token', 'data-csrf-cookie' => 'a"b']);

        $this->assertSame(
            '<script src="/waypoint.js" data-csrf-header="X-My-Token" data-csrf-cookie="a&quot;b"></script>' . "\n",
            $view->waypointJsTag()
        );
    }

    public function testWaypointJsTagIsEmptyAgainWhenExplicitlyResetToNull(): void
    {
        $view = new View('HomePage');
        $view->setWaypointJsPath('/waypoint.js');
        $view->setWaypointJsPath(null);

        $this->assertSame('', $view->waypointJsTag());
    }

    public function testLayoutAssetTagsAndScopeAttributeStayEmptyForAPartialRenderEvenWhenTheLayoutHasAssets(): void
    {
        // A partial render never includes the layout at all, so its assets
        // are never resolved even if LayoutWithAssets.css/.js exist.
        $this->configureRenderer();
        $view = new View('HomePage', null, partial: true, layout: 'LayoutWithAssets.php');

        $view->render();

        $this->assertSame('', $view->layoutAssetTags());
        $this->assertSame('', $view->layoutScopeAttribute());
    }
}
