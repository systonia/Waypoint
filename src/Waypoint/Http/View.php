<?php

namespace Waypoint\Http;

use Waypoint\Waypoint;
use Waypoint\Environment;
use Waypoint\Csrf;
use Waypoint\Attributes\Inject;
use Waypoint\Options\RendererOptions;
use RuntimeException;

class View
{
    /**
     * Available as $this->env inside a view/layout template, e.g.
     * `<?= $this->env->get('APP_ENV') ?>`. Wired up by
     * Router::injectViewProperties() right before render() -- View is
     * never container-managed itself (a controller just `new`s one up
     * directly), so this is populated per-render rather than at
     * construction time.
     *
     * @var Environment
     */
    #[Inject]
    protected Environment $env;

    /**
     * Available as $this->csrf inside a view/layout template, e.g.
     * `<?= $this->csrf->field() ?>` for a classic no-JS <form>, or
     * `$this->csrf->token()` for the raw value (e.g. to hand to
     * client-side JS via a data attribute for the AJAX path). Wired up by
     * Router::injectViewProperties() the same way -- and already carrying
     * this request's resolved token by the time it's injected, since
     * Router::renderView() calls Csrf::issueFor() first (see there).
     *
     * @var Csrf
     */
    #[Inject]
    protected Csrf $csrf;

    /**
     * The view name passed to the constructor, e.g. "ProductDetail".
     *
     * @var string
     */
    protected string $view;

    /**
     * Whatever was passed to the constructor as $model -- commonly an
     * array, but never itself constrained by View.
     *
     * @var mixed
     */
    protected mixed $model;

    /**
     * @var bool
     */
    protected bool $partial;

    /**
     * The resolved layout template's basename (e.g. "_Layout.php"), or
     * null for no layout -- see the constructor's own $layout parameter.
     *
     * @var string|null
     */
    protected ?string $layout = null;

    /**
     * @var array<string, string>
     */
    protected $sections = [];

    /**
     * The name of the section currently being captured via
     * startSection(), or null when none is open.
     *
     * @var string|null
     */
    protected ?string $currentSection = null;

    /**
     * Undocumented variable
     *
     * @var integer
     */
    protected $sectionBufferLevel = 0;

    /**
     * @param string $view
     * @param mixed $model
     * @param bool $partial
     * @param string|null $layout
     */
    public function __construct(string $view, mixed $model = null, bool $partial = false, ?string $layout = null)
    {
        $this->view = $view;
        $this->model = $model;
        $this->partial = $partial;

        if ($layout !== null) {
            $this->layout = $layout;
        }
    }

    /**
     * {css: ?string, js: ?string} cache-busted filenames -- set by
     * Router::renderResult() from the same lookup that drives the
     * X-Waypoint-View-Css/Js response headers, before render() runs.
     *
     * @var array{css: ?string, js: ?string}
     */
    protected array $assets = ['css' => null, 'js' => null];

    /**
     * The URL path prefix assetTags()/layoutAssetTags() build hrefs/srcs
     * under, e.g. "/assets" -- mirrors FileSystemOptions::$assetsPath.
     * Defaults to the framework's own default so a View constructed and
     * rendered directly (bypassing Router, e.g. in a unit test) still
     * produces correct output; Router::renderResult() overwrites it with
     * whatever assetsPath is actually configured right before render().
     *
     * @var string
     */
    protected string $assetsPath = '/assets';

    /**
     * The compiled URL path for Waypoint\UI\WaypointController's own
     * route (e.g. "/waypoint.js"), or null when that controller was never
     * attached -- set by Router::renderView() from
     * Router::resolveWaypointJsPath(). Null by default so a View
     * constructed and rendered directly (bypassing Router) renders no tag
     * at all, the same "safe until told otherwise" default $assetsPath
     * uses, just inverted: there's no sensible default *path* to guess at
     * for a controller that might not even be attached.
     *
     * @var string|null
     */
    protected ?string $waypointJsPath = null;

    /**
     * The resolved layout template's own basename (no .php), e.g.
     * "_Layout" or "AltLayout" -- whatever it actually is, set by render()
     * right before including it. Null until then (or for a partial render,
     * which never includes a layout at all).
     *
     * @var string|null
     */
    protected ?string $layoutName = null;

    /**
     * {css: ?string, js: ?string} cache-busted filenames for the layout
     * template itself (ViewAssets::compile() discovers a sibling .css/.js
     * for *any* '*.php' file in the views directory, layouts included --
     * this is the layout's own entry, looked up by $layoutName exactly
     * like $assets is the current view's). Set by render() right before
     * including the layout, so the layout template can call
     * layoutAssetTags()/layoutScopeAttribute() on itself.
     *
     * @var array{css: ?string, js: ?string}
     */
    protected array $layoutAssets = ['css' => null, 'js' => null];

    /** The view name passed to the constructor, e.g. "ProductDetail" -- Router uses this to look up the view's cache-busted CSS/JS filenames, if any. */
    public function getViewName(): string
    {
        return $this->view;
    }

    /**
     * Called by Router::renderResult() before render() -- lets the layout
     * emit this view's own <link>/<script> tags itself (via assetTags())
     * for a full, non-partial page load, where there's no client-side JS
     * running yet to read the equivalent response headers a partial-swap
     * navigation relies on instead.
     *
     * @param array{css: ?string, js: ?string} $assets
     */
    public function setAssets(array $assets): void
    {
        $this->assets = $assets;
    }

    /** Called by Router::renderView() before render() -- see $assetsPath. */
    public function setAssetsPath(string $assetsPath): void
    {
        $this->assetsPath = $assetsPath;
    }

    /** Called by Router::renderView() before render() -- see $waypointJsPath. */
    public function setWaypointJsPath(?string $waypointJsPath): void
    {
        $this->waypointJsPath = $waypointJsPath;
    }

    /**
     * <link>/<script> tags for this view's own CSS/JS, if any. Call from
     * the layout, e.g. `<?= $this->assetTags() ?>` alongside the rest of
     * <head>.
     */
    public function assetTags(): string
    {
        return $this->renderAssetTags($this->assets);
    }

    /**
     * <link>/<script> tags for the layout template's own CSS/JS, if any --
     * the same mechanism as assetTags(), just for whichever file is
     * actually resolved as the layout (see $layoutName), no matter its
     * name. Call from inside the layout template itself, alongside
     * assetTags(): `<?= $this->assetTags() ?><?= $this->layoutAssetTags() ?>`.
     * Always empty for a partial render, since no layout is included then.
     */
    public function layoutAssetTags(): string
    {
        return $this->renderAssetTags($this->layoutAssets);
    }

    /**
     * `<script>` tag for the bundled waypoint.js client (see
     * Waypoint\UI\WaypointController), pointed at wherever that
     * controller's own route actually resolved to -- call from the layout,
     * e.g. `<?= $this->waypointJsTag() ?>`, instead of hardcoding the path
     * yourself. Empty string when WaypointController was never attached
     * (see $waypointJsPath): nothing to link to, so nothing is rendered,
     * rather than a tag pointing at a route that 404s.
     */
    public function waypointJsTag(): string
    {
        if ($this->waypointJsPath === null) {
            return '';
        }

        $src = htmlspecialchars($this->waypointJsPath, ENT_QUOTES);
        return "<script src=\"$src\"></script>\n";
    }

    /**
     * Shared by assetTags()/layoutAssetTags() -- builds <link>/<script> tags for one {css, js} pair.
     * @param array{css: ?string, js: ?string} $assets
     */
    private function renderAssetTags(array $assets): string
    {
        $tags = '';
        if ($assets['css'] !== null) {
            // data-wp-asset must match what waypoint.js's own client-side
            // injection marks its <link>/<script> tags with (see
            // assets.ts's injectOnce()) -- otherwise a tag this method
            // rendered on a hard page load is invisible to the client's
            // dedup check, and navigating away and back injects a second,
            // duplicate tag for the exact same asset.
            $filename = htmlspecialchars($assets['css'], ENT_QUOTES);
            $href = htmlspecialchars($this->assetsPath . '/' . $assets['css'], ENT_QUOTES);
            $tags .= "<link rel=\"stylesheet\" href=\"$href\" data-wp-asset=\"$filename\">\n";
        }
        if ($assets['js'] !== null) {
            $filename = htmlspecialchars($assets['js'], ENT_QUOTES);
            $src = htmlspecialchars($this->assetsPath . '/' . $assets['js'], ENT_QUOTES);
            $tags .= "<script src=\"$src\" data-wp-asset=\"$filename\"></script>\n";
        }
        return $tags;
    }

    /**
     * `data-view="..."` for the layout to place on whichever element wraps
     * `$content`, e.g. `<main <?= $this->scopeAttribute() ?>>` -- exactly
     * what ViewAssets::scopeCss()'s `[data-view="..."]` selectors actually
     * match against. Empty string when this view has no CSS/JS at all, so
     * it's always safe to echo directly into an attribute position.
     */
    public function scopeAttribute(): string
    {
        if ($this->assets['css'] === null && $this->assets['js'] === null) {
            return '';
        }
        return 'data-view="' . htmlspecialchars($this->view, ENT_QUOTES) . '"';
    }

    /**
     * `data-view="<layout-basename>"` for the layout template to place on
     * whichever element its OWN CSS should be scoped to, e.g.
     * `<body <?= $this->layoutScopeAttribute() ?>>` -- same
     * `[data-view="..."]` convention scopeAttribute() uses for the
     * current view, just keyed by the layout's own basename ($layoutName)
     * instead, since ViewAssets::scopeCss() has no separate concept of
     * "layout" -- every '*.php' file's CSS is scoped by its own basename
     * the same way. Empty string when the layout has no CSS/JS of its own.
     */
    public function layoutScopeAttribute(): string
    {
        if ($this->layoutAssets['css'] === null && $this->layoutAssets['js'] === null) {
            return '';
        }
        return 'data-view="' . htmlspecialchars($this->layoutName ?? '', ENT_QUOTES) . '"';
    }

    /**
     * @param string $name
     * @return void
     */
    public function startSection(string $name): void
    {
        if ($this->currentSection !== null) {
            throw new RuntimeException("A section is already started: '{$this->currentSection}'");
        }
        $this->currentSection = $name;
        $this->sectionBufferLevel = ob_get_level();
        ob_start();
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public function endSection(): void
    {
        if ($this->currentSection === null) {
            throw new RuntimeException("No section is currently started.");
        }
        // @codeCoverageIgnoreStart
        // ob_get_clean() is only typed to allow false for when there's no
        // active output buffer to pop -- can't happen right after the
        // ob_start() in startSection() above.
        $content = ob_get_clean() ?: '';
        // @codeCoverageIgnoreEnd
        $this->sections[$this->currentSection] = $content;
        $this->currentSection = null;
    }

    /**
     * @param string $name
     * @param boolean $required
     * @return bool|string
     */
    public function section(string $name, bool $required = false): bool|string
    {
        if (isset($this->sections[$name])) {
            return $this->sections[$name];
        }
        if ($required) {
            throw new RuntimeException("Section '{$name}' is required but not defined.");
        }
        return '';
    }

    public function render(): string
    {
        $model = $this->model;
        $viewFile = Waypoint::getConfig(RendererOptions::class)->directory . "/{$this->view}.php";
        if (!file_exists($viewFile)) {
            throw new RuntimeException("View '{$viewFile}' not found.");
        }

        // Render view (inside $this context)
        ob_start();
        include $viewFile;
        // @codeCoverageIgnoreStart
        // ob_get_clean() is only typed to allow false for when there's no
        // active output buffer to pop -- can't happen right after the
        // ob_start() immediately above.
        $content = ob_get_clean() ?: '';
        // @codeCoverageIgnoreEnd

        if ($this->layout !== null) {
            $layoutFile = Waypoint::getConfig(RendererOptions::class)->directory . '/' . $this->layout;
        } else {
            $layoutFile = Waypoint::getConfig(RendererOptions::class)->directory . '/' . Waypoint::getConfig(RendererOptions::class)->layout;
        }

        if (!$this->partial && file_exists($layoutFile)) {
            $this->layoutName = basename($layoutFile, '.php');

            // Router::getViewAssets() is only reachable once attach() has
            // run -- View::render() is also used directly (e.g. in unit
            // tests) with no App/Router involved at all, so this degrades
            // to "no layout assets" rather than throwing when there isn't
            // one, the same tolerant contract getViewAssets() itself has
            // for a name it's never seen.
            $app = Waypoint::getInstance();
            if ($app !== null && $app->hasRouter()) {
                $this->layoutAssets = $app->getRouter()->getViewAssets($this->layoutName);
            }

            ob_start();
            include $layoutFile;
            // @codeCoverageIgnoreStart
            return ob_get_clean() ?: '';
            // @codeCoverageIgnoreEnd
        } else {
            return $content;
        }
    }
}
