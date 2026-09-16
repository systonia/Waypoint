<?php

namespace Waypoint\Http;

use BadMethodCallException;
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

    /** URL of the compiled waypoint.js asset, set by Router::renderView(); null only for a View rendered outside a Router. */
    protected ?string $waypointJsPath = null;

    /** @var array<string, string> data-* attributes waypoint.js reads off its own <script> tag (see Router::renderView()). */
    protected array $waypointJsAttributes = [];

    /** The layout's basename without .php, set by render() (null for a partial render). */
    protected ?string $layoutName = null;

    /** @var array{css: ?string, js: ?string} The layout's own compiled assets (every views/*.php file gets the same sibling lookup). */
    protected array $layoutAssets = ['css' => null, 'js' => null];

    /** @var array<string, callable> Plugin view helpers (Plugin\ViewHelper), reachable as $this->name(...) via __call(). */
    protected array $helpers = [];

    /** @var list<string> URLs of plugin CSS/JS (Plugin\ClientAsset), set by Router::renderView(). */
    protected array $pluginAssetUrls = [];

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

    /** @param array<string, string> $attributes Extra attributes for the tag, e.g. ['data-csrf-cookie' => 'my_token']. */
    public function setWaypointJsPath(?string $waypointJsPath, array $attributes = []): void
    {
        $this->waypointJsPath = $waypointJsPath;
        $this->waypointJsAttributes = $attributes;
    }

    /** @param array<string, callable> $helpers */
    public function setHelpers(array $helpers): void
    {
        $this->helpers = $helpers;
    }

    /** @param list<string> $urls */
    public function setPluginAssetUrls(array $urls): void
    {
        $this->pluginAssetUrls = $urls;
    }

    /**
     * `$this->t('key')` and any other helper a plugin registered.
     * @param list<mixed> $arguments
     */
    public function __call(string $name, array $arguments): mixed
    {
        $helper = $this->helpers[$name] ?? null;
        if ($helper === null) {
            throw new BadMethodCallException("View helper '{$name}' is not provided by any registered plugin.");
        }
        return $helper(...$arguments);
    }

    /** `<link>`/`<script>` tags for every plugin-shipped asset -- place after waypointJsTag(). */
    public function pluginAssetTags(): string
    {
        $tags = '';
        foreach ($this->pluginAssetUrls as $url) {
            $escaped = htmlspecialchars($url, ENT_QUOTES);
            $tags .= str_ends_with($url, '.css')
                ? "<link rel=\"stylesheet\" href=\"$escaped\">\n"
                : "<script src=\"$escaped\"></script>\n";
        }
        return $tags;
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

    /**
     * `<script>` tag for the bundled waypoint.js client (content-hashed, served like any view asset).
     * Carries the CSRF cookie/header names as data attributes when CsrfOptions differs from the
     * client's defaults, so the layout never repeats what the options already say.
     */
    public function waypointJsTag(): string
    {
        if ($this->waypointJsPath === null) {
            return '';
        }
        $tag = '<script src="' . htmlspecialchars($this->waypointJsPath, ENT_QUOTES) . '"';
        foreach ($this->waypointJsAttributes as $name => $value) {
            $tag .= ' ' . htmlspecialchars($name, ENT_QUOTES) . '="' . htmlspecialchars($value, ENT_QUOTES) . '"';
        }
        return $tag . "></script>\n";
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
