<?php

namespace Waypoint\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Waypoint\ViewAssets;

/**
 * Direct unit coverage for ViewAssets, independent of Router/FileSystem's
 * caching machinery -- RouterDispatchTest exercises the same class through
 * a real request, but which code path (fresh compile vs. cache-hit) that
 * takes depends on what's already sitting in the shared temp cache
 * directory from earlier tests, so it can't reliably exercise every branch
 * here on its own.
 */
final class ViewAssetsTest extends TestCase
{
    private const FIXTURES_DIR = __DIR__ . '/../Fixtures/ViewAssets';

    public function testDiscoverViewNamesReturnsEmptyArrayForANonexistentDirectory(): void
    {
        $this->assertSame([], ViewAssets::discoverViewNames(self::FIXTURES_DIR . '/does-not-exist'));
    }

    public function testDiscoverViewNamesReturnsEveryPhpFileAtAnyDepthAsASlashedRelativeName(): void
    {
        $names = ViewAssets::discoverViewNames(self::FIXTURES_DIR);

        sort($names);
        $this->assertSame(
            ['CssOnly', 'JsOnly', 'Nested/CssOnly', 'Nested/Deeper/Plain', 'NoAssets', 'WithBoth'],
            $names
        );
    }

    public function testCompileFindsANestedViewsSiblingCssUnderItsSlashedName(): void
    {
        $compiled = ViewAssets::compile(self::FIXTURES_DIR);

        $this->assertArrayHasKey('Nested/CssOnly', $compiled['views']);
        $this->assertNull($compiled['views']['Nested/CssOnly']['js']);
        $this->assertArrayNotHasKey('Nested/Deeper/Plain', $compiled['views']);

        $css = $compiled['files'][$compiled['views']['Nested/CssOnly']['css']]['content'];
        $this->assertStringContainsString('[data-view="Nested/CssOnly"] {', $css);
    }

    public function testDiscoverMetaOnlyIncludesFilesThatActuallyExist(): void
    {
        $meta = ViewAssets::discoverMeta(self::FIXTURES_DIR);

        $this->assertArrayHasKey(self::FIXTURES_DIR . '/WithBoth.css', $meta);
        $this->assertArrayHasKey(self::FIXTURES_DIR . '/WithBoth.js', $meta);
        $this->assertArrayHasKey(self::FIXTURES_DIR . '/CssOnly.css', $meta);
        $this->assertArrayNotHasKey(self::FIXTURES_DIR . '/CssOnly.js', $meta);
        $this->assertArrayNotHasKey(self::FIXTURES_DIR . '/NoAssets.css', $meta);
        $this->assertArrayNotHasKey(self::FIXTURES_DIR . '/NoAssets.js', $meta);
    }

    public function testCompileHashesBothSiblingsWhenBothExist(): void
    {
        $compiled = ViewAssets::compile(self::FIXTURES_DIR);

        $this->assertMatchesRegularExpression('/^[a-f0-9]{12}\.css$/', $compiled['views']['WithBoth']['css']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{12}\.js$/', $compiled['views']['WithBoth']['js']);

        $cssFilename = $compiled['views']['WithBoth']['css'];
        $this->assertSame(
            "[data-view=\"WithBoth\"] {\n    .with-both { color: red; }\n}\n",
            $compiled['files'][$cssFilename]['content'],
        );
        $this->assertSame('text/css; charset=utf-8', $compiled['files'][$cssFilename]['mime']);

        $jsFilename = $compiled['views']['WithBoth']['js'];
        $this->assertSame("console.log('with-both');\n", $compiled['files'][$jsFilename]['content']);
        $this->assertSame('application/javascript; charset=utf-8', $compiled['files'][$jsFilename]['mime']);
    }

    public function testCompileLeavesJsNullWhenOnlyCssExists(): void
    {
        $compiled = ViewAssets::compile(self::FIXTURES_DIR);

        $this->assertNotNull($compiled['views']['CssOnly']['css']);
        $this->assertNull($compiled['views']['CssOnly']['js']);
    }

    public function testCompileLeavesCssNullWhenOnlyJsExists(): void
    {
        $compiled = ViewAssets::compile(self::FIXTURES_DIR);

        $this->assertNull($compiled['views']['JsOnly']['css']);
        $this->assertNotNull($compiled['views']['JsOnly']['js']);
    }

    public function testCompileExcludesViewsWithNeitherSibling(): void
    {
        $compiled = ViewAssets::compile(self::FIXTURES_DIR);

        $this->assertArrayNotHasKey('NoAssets', $compiled['views']);
    }

    public function testCompileSameContentAlwaysHashesToTheSameFilename(): void
    {
        $first = ViewAssets::compile(self::FIXTURES_DIR);
        $second = ViewAssets::compile(self::FIXTURES_DIR);

        $this->assertSame($first['views']['WithBoth']['css'], $second['views']['WithBoth']['css']);
    }

    public function testScopeCssWrapsOrdinaryRulesInOneNestingParent(): void
    {
        $scoped = ViewAssets::scopeCss('h1, h2 { margin: 0; }', 'Home');

        $this->assertSame("[data-view=\"Home\"] {\n    h1, h2 { margin: 0; }\n}\n", $scoped);
    }

    public function testScopeCssNeverSplitsACommaInsideAFunctionalPseudoClass(): void
    {
        // The bug the previous per-selector-rewrite implementation had:
        // splitting a selector list on every comma breaks as soon as one
        // selector uses a functional pseudo-class with its own comma.
        // Wrapping instead of rewriting means there's no selector text to
        // split at all -- the browser's own nesting resolution handles it.
        $css = ':is(.a, .b) > .c { color: red; }';

        $this->assertSame(
            "[data-view=\"Home\"] {\n    :is(.a, .b) > .c { color: red; }\n}\n",
            ViewAssets::scopeCss($css, 'Home'),
        );
    }

    public function testScopeCssNestsAMediaBlockInsideTheWrapperUnchanged(): void
    {
        // CSS Nesting is explicitly spec'd to work inside @media/@supports/
        // @container too -- the wrapper doesn't need to know @media exists.
        $css = '@media (max-width: 600px) { .card { padding: 4px; } }';

        $this->assertSame(
            "[data-view=\"Home\"] {\n    @media (max-width: 600px) { .card { padding: 4px; } }\n}\n",
            ViewAssets::scopeCss($css, 'Home'),
        );
    }

    public function testScopeCssLeavesKeyframesCompletelyUnwrapped(): void
    {
        // @keyframes is a global construct, not a selector -- CSS Nesting
        // doesn't allow nesting it inside a style rule, so it has to stay
        // outside the wrapper entirely.
        $css = '@keyframes spin { 0% { opacity: 0; } 100% { opacity: 1; } }';

        $this->assertSame($css, ViewAssets::scopeCss($css, 'Home'));
    }

    public function testScopeCssLeavesFontFaceCompletelyUnwrapped(): void
    {
        $css = '@font-face { font-family: "Body"; src: url(body.woff2); }';

        $this->assertSame($css, ViewAssets::scopeCss($css, 'Home'));
    }

    public function testScopeCssWrapsOnlyTheRestAfterAKeyframesBlock(): void
    {
        $scoped = ViewAssets::scopeCss('@keyframes spin { 100% { opacity: 1; } } .card { color: red; }', 'Home');

        $this->assertSame(
            "@keyframes spin { 100% { opacity: 1; } }[data-view=\"Home\"] {\n     .card { color: red; }\n}\n",
            $scoped,
        );
    }

    public function testScopeCssHandlesACommentContainingACommaWithoutBreaking(): void
    {
        // Regression test: this exact shape (a file-header comment whose
        // prose contains commas) is what waypoint-bakery's ProductDetail.css
        // actually has -- verifying it's inert now that comments just ride
        // along inside the wrapper rather than needing to be parsed at all.
        $css = "/* a note, with a comma, right here */\n.price { color: red; }";

        $this->assertSame(
            "[data-view=\"ProductDetail\"] {\n    /* a note, with a comma, right here */\n    .price { color: red; }\n}\n",
            ViewAssets::scopeCss($css, 'ProductDetail'),
        );
    }

    public function testScopeCssLeavesABareSlashInUrlAndFontShorthandAlone(): void
    {
        $css = '.a { background: url(http://example.com/x.png); font: 12px/1.5 sans-serif; }';

        $this->assertSame(
            "[data-view=\"Home\"] {\n    $css\n}\n",
            ViewAssets::scopeCss($css, 'Home'),
        );
    }

    public function testScopeCssHandlesAnUnclosedKeyframesBlockWithoutCrashing(): void
    {
        // Malformed/truncated CSS -- an @keyframes block that never closes
        // before the file ends (note the trailing space after the last
        // brace, so end-of-string is reached mid-chunk rather than
        // exactly on a brace). Must not crash; everything from the
        // unclosed block onward is unscopable (there's no valid "rest"
        // left to wrap).
        $css = '@keyframes spin { 0% { opacity: 0; } ';

        $this->assertSame($css, ViewAssets::scopeCss($css, 'Home'));
    }

    public function testScopeCssEscapesAQuoteInTheViewName(): void
    {
        $scoped = ViewAssets::scopeCss('.card { color: red; }', 'Weird"Name');

        $this->assertStringStartsWith('[data-view="Weird\\"Name"] {', $scoped);
    }
}
