<?php

namespace Waypoint\Http;

use InvalidArgumentException;
use RuntimeException;
use Waypoint\{Csrf, Environment, Waypoint};
use Waypoint\Attributes\Inject;
use Waypoint\Options\RendererOptions;

/**
 * A server-rendered template result: `return new View('Admin/Users', $model)`
 * renders views/Admin/Users.php inside RendererOptions::$layout (unless
 * $partial). Inside a template `$this` is this View and `$model` the model;
 * `$this->env`/`$this->csrf` are injected by Router before render().
 */
class View
{
    /** Available inside templates as `$this->env`. */
    #[Inject]
    protected Environment $env;

    /** Available inside templates as `$this->csrf` (already carrying this request's token). */
    #[Inject]
    protected Csrf $csrf;

    protected string $view;
    protected mixed $model;
    protected bool $partial;

    /** Explicit layout file basename (e.g. "_Layout.php"), or null for RendererOptions::$layout. */
    protected ?string $layout = null;

    /** @var array<string, string> */
    protected array $sections = [];
    protected ?string $currentSection = null;
    protected int $sectionBufferLevel = 0;

    /** @var array{css: ?string, js: ?string} This view's compiled asset filenames, set by Router::renderView(). */
    protected array $assets = ['css' => null, 'js' => null];

    /** URL prefix assets are served under; Router overwrites it with FileSystemOptions::$assetsPath. */
    protected string $assetsPath = '/assets';

    /** Compiled path of WaypointController's route, or null when it isn't attached (then waypointJsTag() renders nothing). */
    protected ?string $waypointJsPath = null;

    /** The layout's basename without .php, set by render() (null for a partial render). */
    protected ?string $layoutName = null;

    /** @var array{css: ?string, js: ?string} The layout's own compiled assets (every views/*.php file gets the same sibling lookup). */
    protected array $layoutAssets = ['css' => null, 'js' => null];

    /**
     * @param string $view Path under RendererOptions::$directory without .php, '/'-separated ("Admin/Users").
     *  A leading '/', a backslash or a '..' segment is rejected: the name is often built from request input.
     * @param string|null $layout Overrides RendererOptions::$layout for this render.
     */
    public function __construct(string $view, mixed $model = null, bool $partial = false, ?string $layout = null)
    {
        if ($view === '' || str_starts_with($view, '/') || str_contains($view, '\\') || in_array('..', explode('/', $view), true)) {
            throw new InvalidArgumentException("Invalid view name '{$view}': must be a '/'-separated path inside the views directory, without '..' segments.");
        }
        $this->view = $view;
        $this->model = $model;
        $this->partial = $partial;
        $this->layout = $layout;
    }

    public function getViewName(): string
    {
        return $this->view;
    }

    /** @param array{css: ?string, js: ?string} $assets */
    public function setAssets(array $assets): void
    {
        $this->assets = $assets;
    }

    public function setAssetsPath(string $assetsPath): void
    {
        $this->assetsPath = $assetsPath;
    }

    public function setWaypointJsPath(?string $waypointJsPath): void
    {
        $this->waypointJsPath = $waypointJsPath;
    }

    /** `<link>`/`<script>` tags for this view's own CSS/JS -- for the layout's `<head>`. */
    public function assetTags(): string
    {
        return $this->renderAssetTags($this->assets);
    }

    /** Same for the layout's own CSS/JS; empty in a partial render. */
    public function layoutAssetTags(): string
    {
        return $this->renderAssetTags($this->layoutAssets);
    }

    /** `<script>` tag for the bundled waypoint.js client, or '' when WaypointController isn't attached. */
    public function waypointJsTag(): string
    {
        if ($this->waypointJsPath === null) {
            return '';
        }
        return '<script src="' . htmlspecialchars($this->waypointJsPath, ENT_QUOTES) . "\"></script>\n";
    }

    /**

     * data-wp-asset must match what waypoint.js marks its own injected tags with, or a later swap would inject a duplicate.

     * @param array{css: ?string, js: ?string} $assets

     */
    private function renderAssetTags(array $assets): string
    {
        $tags = '';
        if ($assets['css'] !== null) {
            $tags .= sprintf("<link rel=\"stylesheet\" href=\"%s\" data-wp-asset=\"%s\">\n", htmlspecialchars("{$this->assetsPath}/{$assets['css']}", ENT_QUOTES), htmlspecialchars($assets['css'], ENT_QUOTES));
        }
        if ($assets['js'] !== null) {
            $tags .= sprintf("<script src=\"%s\" data-wp-asset=\"%s\"></script>\n", htmlspecialchars("{$this->assetsPath}/{$assets['js']}", ENT_QUOTES), htmlspecialchars($assets['js'], ENT_QUOTES));
        }
        return $tags;
    }

    /** `data-view="..."` for the element wrapping `$content` -- what the view's scoped CSS matches on; '' when it has no assets. */
    public function scopeAttribute(): string
    {
        return $this->scopeAttributeFor($this->assets, $this->view);
    }

    /** Same for the layout's own scoped CSS, e.g. on `<body>`. */
    public function layoutScopeAttribute(): string
    {
        return $this->scopeAttributeFor($this->layoutAssets, $this->layoutName ?? '');
    }

    /** @param array{css: ?string, js: ?string} $assets */
    private function scopeAttributeFor(array $assets, string $name): string
    {
        if ($assets['css'] === null && $assets['js'] === null) {
            return '';
        }
        return 'data-view="' . htmlspecialchars($name, ENT_QUOTES) . '"';
    }

    public function startSection(string $name): void
    {
        if ($this->currentSection !== null) {
            throw new RuntimeException("A section is already started: '{$this->currentSection}'");
        }
        $this->currentSection = $name;
        $this->sectionBufferLevel = ob_get_level();
        ob_start();
    }

    public function endSection(): void
    {
        if ($this->currentSection === null) {
            throw new RuntimeException("No section is currently started.");
        }
        // @codeCoverageIgnoreStart
        // ob_get_clean() can't fail right after startSection()'s ob_start().
        $this->sections[$this->currentSection] = ob_get_clean() ?: '';
        // @codeCoverageIgnoreEnd
        $this->currentSection = null;
    }

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
        $options = Waypoint::getConfig(RendererOptions::class);
        $viewFile = "{$options->directory}/{$this->view}.php";
        if (!file_exists($viewFile)) {
            throw new RuntimeException("View '{$viewFile}' not found.");
        }

        ob_start();
        include $viewFile;
        // @codeCoverageIgnoreStart
        $content = ob_get_clean() ?: '';
        // @codeCoverageIgnoreEnd

        $layoutFile = "{$options->directory}/" . ($this->layout ?? $options->layout);
        if ($this->partial || !file_exists($layoutFile)) {
            return $content;
        }

        $this->layoutName = basename($layoutFile, '.php');
        // A View rendered outside a Router (unit tests) simply has no layout assets.
        $app = Waypoint::getInstance();
        if ($app !== null && $app->hasRouter()) {
            $this->layoutAssets = $app->getRouter()->getViewAssets($this->layoutName);
        }

        ob_start();
        include $layoutFile;
        // @codeCoverageIgnoreStart
        return ob_get_clean() ?: '';
        // @codeCoverageIgnoreEnd
    }
}
