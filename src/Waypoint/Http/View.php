<?php

namespace Waypoint\Http;

use BadMethodCallException;
use Closure;
use InvalidArgumentException;
use RuntimeException;
use Stringable;
use Throwable;
use TypeError;
use WeakMap;
use Waypoint\{Csrf, Environment, Html, Waypoint};
use Waypoint\Attributes\Inject;
use Waypoint\Options\{EnvironmentOptions, FileSystemOptions, RendererOptions};
use Waypoint\View\Compiler;

/**
 * A server-rendered template result: `return new View('Backoffice/Sales', $model)`
 * renders views/Backoffice/Sales.php inside RendererOptions::$layout (unless
 * $partial). Inside a template `$this` is this View and `$model` the model;
 * `$this->env`/`$this->csrf`/`$this->request` are set by Router before render().
 * `{{ $value }}` and `<?= $value ?>` are HTML-escaped; Waypoint\Html (what section(),
 * assetTags() and the like return) and `<?php echo ?>` print as they are. The
 * template syntax (`@if`, `@foreach`, `@section`, `@model`, ...) is Compiler's.
 */
class View
{
    /** Available inside templates as `$this->env`. */
    #[Inject]
    protected Environment $env;

    /** Available inside templates as `$this->csrf` (already carrying this request's token). */
    #[Inject]
    protected Csrf $csrf;

    /** The request being answered, inside templates `$this->request`; set by Router::renderView(), null for a View rendered outside one. */
    public private(set) ?Request $request = null;

    protected string $view;
    protected mixed $model;
    protected bool $partial;

    /** Explicit layout file basename (e.g. "_Layout.php"), or null for RendererOptions::$layout. */
    protected ?string $layout = null;

    /** #[Layout(null)] on the route: no layout, unless the View names one. */
    protected bool $withoutLayout = false;

    /** A directory of its own for view and layout, e.g. a plugin's views; null for RendererOptions::$directory. */
    protected ?string $directory = null;

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

    /** @var array<string, string> data-* attributes waypoint.js reads off its own `<script>` tag (see Router::renderView()). */
    protected array $waypointJsAttributes = [];

    /** The layout's basename without .php, set by render() (null for a partial render). */
    protected ?string $layoutName = null;

    /** @var array{css: ?string, js: ?string} The layout's own compiled assets (every views/*.php file gets the same sibling lookup). */
    protected array $layoutAssets = ['css' => null, 'js' => null];

    /** @var array<string, callable> Plugin view helpers (Plugin\ViewHelper), reachable as $this->name(...) via __call(). */
    protected array $helpers = [];

    /** @var list<string> URLs of plugin CSS/JS (Plugin\ClientAsset), set by Router::renderView(). */
    protected array $pluginAssetUrls = [];

    /** @var array<string, array{int, string}> template path => its mtime and compiled code, without a cache directory */
    private static array $compiled = [];

    /** @var WeakMap<Throwable, array{string, int}>|null The view and line an error came from */
    private static ?WeakMap $origins = null;

    /**
     * @param string $view Path under RendererOptions::$directory without .php, '/'-separated ("Backoffice/Sales").
     *  A leading '/', a backslash or a '..' segment is rejected: the name is often built from request input.
     * @param string|null $layout Overrides RendererOptions::$layout for this render.
     * @param string|null $directory Overrides RendererOptions::$directory for view and layout, e.g. a plugin's own
     *  views. Such a view gets no compiled CSS/JS of its own.
     */
    public function __construct(string $view, mixed $model = null, bool $partial = false, ?string $layout = null, ?string $directory = null)
    {
        if ($view === '' || str_starts_with($view, '/') || str_contains($view, '\\') || in_array('..', explode('/', $view), true)) {
            throw new InvalidArgumentException("Invalid view name '{$view}': must be a '/'-separated path inside the views directory, without '..' segments.");
        }
        $this->view = $view;
        $this->model = $model;
        $this->partial = $partial;
        $this->layout = $layout;
        $this->directory = $directory;
    }

    /**
     * The route's #[Layout]: its file, or null for none. A layout passed to the constructor stays.
     *
     * @internal Router calls it before render()
     */
    public function useRouteLayout(?string $file): void
    {
        if ($this->layout !== null) {
            return;
        }
        if ($file === null) {
            $this->withoutLayout = true;
            return;
        }
        $this->layout = $file;
    }

    public function getDirectory(): ?string
    {
        return $this->directory;
    }

    public function getViewName(): string
    {
        return $this->view;
    }

    public function setRequest(Request $request): void
    {
        $this->request = $request;
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

    /** `<link>`/`<script>` tags for every plugin-shipped asset; place after waypointJsTag(). */
    public function pluginAssetTags(): Html
    {
        $tags = '';
        foreach ($this->pluginAssetUrls as $url) {
            $escaped = htmlspecialchars($url, ENT_QUOTES);
            $tags .= str_ends_with($url, '.css')
                ? "<link rel=\"stylesheet\" href=\"$escaped\">\n"
                : "<script src=\"$escaped\"></script>\n";
        }
        return new Html($tags);
    }

    /** `<link>`/`<script>` tags for this view's own CSS/JS, for the layout's `<head>`. */
    public function assetTags(): Html
    {
        return new Html($this->renderAssetTags($this->assets));
    }

    /** Same for the layout's own CSS/JS; empty in a partial render. */
    public function layoutAssetTags(): Html
    {
        return new Html($this->renderAssetTags($this->layoutAssets));
    }

    /**
     * `<script>` tag for the bundled waypoint.js client (content-hashed, served like any view asset).
     * Carries the CSRF cookie/header names as data attributes when CsrfOptions differs from the
     * client's defaults, so the layout never repeats what the options already say.
     */
    public function waypointJsTag(): Html
    {
        if ($this->waypointJsPath === null) {
            return new Html('');
        }
        $tag = '<script src="' . htmlspecialchars($this->waypointJsPath, ENT_QUOTES) . '"';
        foreach ($this->waypointJsAttributes as $name => $value) {
            $tag .= ' ' . htmlspecialchars($name, ENT_QUOTES) . '="' . htmlspecialchars($value, ENT_QUOTES) . '"';
        }
        return new Html($tag . "></script>\n");
    }

    /** @param array{css: ?string, js: ?string} $assets */
    private function renderAssetTags(array $assets): string
    {
        $tags = '';
        if ($assets['css'] !== null) {
            $tags .= sprintf("<link rel=\"stylesheet\" href=\"%s\">\n", htmlspecialchars("{$this->assetsPath}/{$assets['css']}", ENT_QUOTES));
        }
        if ($assets['js'] !== null) {
            $tags .= sprintf("<script src=\"%s\"></script>\n", htmlspecialchars("{$this->assetsPath}/{$assets['js']}", ENT_QUOTES));
        }
        return $tags;
    }

    /** `data-view="..."` for the element wrapping `$content` (what the view's scoped CSS matches on); '' when it has no assets. */
    public function scopeAttribute(): Html
    {
        return $this->scopeAttributeFor($this->assets, $this->view);
    }

    /** Same for the layout's own scoped CSS, e.g. on `<body>`. */
    public function layoutScopeAttribute(): Html
    {
        return $this->scopeAttributeFor($this->layoutAssets, $this->layoutName ?? '');
    }

    /** @param array{css: ?string, js: ?string} $assets */
    private function scopeAttributeFor(array $assets, string $name): Html
    {
        if ($assets['css'] === null && $assets['js'] === null) {
            return new Html('');
        }
        return new Html('data-view="' . htmlspecialchars($name, ENT_QUOTES) . '"');
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

    /** The section's markup, empty when it was never started (or an exception for a $required one). */
    public function section(string $name, bool $required = false): Html
    {
        if (isset($this->sections[$name])) {
            return new Html($this->sections[$name]);
        }
        if ($required) {
            throw new RuntimeException("Section '{$name}' is required but not defined.");
        }
        return new Html('');
    }

    /** Whether the view started the section, e.g. to leave out its wrapper. */
    public function hasSection(string $name): bool
    {
        return isset($this->sections[$name]);
    }

    /**
     * Renders the view, then the layout around it. Should a template throw, every output buffer it
     * opened (its own and any unfinished section) is discarded: the half-rendered page must neither
     * end up in the error response nor stay open in a persistent worker for the next request.
     */
    public function render(): string
    {
        $level = ob_get_level();
        try {
            return $this->renderUnguarded();
        } catch (\Throwable $e) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
            $this->currentSection = null;
            throw $e;
        }
    }

    private function renderUnguarded(): string
    {
        $options = Waypoint::getConfig(RendererOptions::class);
        $directory = $this->directory ?? $options->directory;
        $viewFile = "{$directory}/{$this->view}.php";
        if (!file_exists($viewFile)) {
            throw new RuntimeException("View '{$viewFile}' not found.");
        }

        $content = $this->renderFile($viewFile, $this->model);

        $layoutFile = "{$directory}/" . ($this->layout ?? $options->layout);
        if ($this->partial || $this->withoutLayout || !file_exists($layoutFile)) {
            return $content;
        }

        $this->layoutName = basename($layoutFile, '.php');
        // A View rendered outside a Router (unit tests) simply has no layout assets.
        $app = Waypoint::getInstance();
        if ($app !== null && $app->hasRouter() && $this->directory === null) {
            $this->layoutAssets = $app->getRouter()->getViewAssets($this->layoutName);
        }

        return $this->renderFile($layoutFile, $this->model, new Html($content));
    }

    /**
     * Runs a template, compiled (see compile()), with `$this`, `$model` and, in a layout, `$content` in scope. With a
     * cache directory the compiled template is a file there, which OPcache keeps; without one it is evaluated.
     */
    private function renderFile(string $file, mixed $model, ?Html $content = null): string
    {
        [$compiledFile, $code] = self::compiled($file);
        ob_start();
        try {
            if ($compiledFile !== null) {
                include $compiledFile;
            } else {
                eval('?>' . $code);
            }
        } catch (Throwable $e) {
            // The innermost view keeps it: a view a helper renders runs inside another
            // Evaluated, the code's file is this one's with the line of the eval: View.php(326) : eval()'d code
            $line = self::lineIn($e, $compiledFile ?? __FILE__ . '(');
            self::$origins ??= new WeakMap();
            if ($line !== null && !isset(self::$origins[$e])) {
                self::$origins[$e] = [$file, $line];
            }
            throw $e;
        }
        // @codeCoverageIgnoreStart
        return ob_get_clean() ?: '';
        // @codeCoverageIgnoreEnd
    }

    /**
     * The view file and line an error of a render came from: where it was thrown, or where the view called what
     * threw it. The compiler keeps the lines, so they are the view's. For the developer: logs, the editor.
     *
     * @return array{string, int}|null
     */
    public static function origin(Throwable $e): ?array
    {
        return self::$origins[$e] ?? null;
    }

    /** The line of the compiled view (the file, or what it starts with) $e was thrown in or passed through, or null. */
    private static function lineIn(Throwable $e, string $compiled): ?int
    {
        if (str_starts_with($e->getFile(), $compiled)) {
            return $e->getLine();
        }
        foreach ($e->getTrace() as $frame) {
            if (str_starts_with($frame['file'] ?? '', $compiled)) {
                return $frame['line'] ?? null;
            }
        }
        return null;
    }

    /** @internal What `@model (Type)` runs: in development a model of another type is a TypeError. */
    protected function assertModel(Closure $check, string $type): void
    {
        if (!Waypoint::getConfig(EnvironmentOptions::class)->isDev()) {
            return;
        }
        try {
            $check($this->model);
        } catch (TypeError) {
            throw new TypeError("@model ($type): the model is " . get_debug_type($this->model));
        }
    }

    /**
     * The compiled template: [its file in the cache directory, null], or [null, its code] without a cache directory.
     * Rebuilt when the template changes, or Waypoint (this file) does.
     * @return array{0: ?string, 1: string}
     */
    private static function compiled(string $file): array
    {
        $modified = (int) filemtime($file);
        $fileSystem = Waypoint::getInstance()?->getContainer()->get(FileSystemOptions::class);
        if ($fileSystem === null || !$fileSystem->isCacheEnabled()) {
            if ((self::$compiled[$file][0] ?? null) !== $modified) {
                self::$compiled[$file] = [$modified, self::compile((string) file_get_contents($file), $file)];
            }
            return [null, self::$compiled[$file][1]];
        }
        $directory = $fileSystem->getCacheDirectory() . '/views';
        $compiled = $directory . '/' . md5($file . '|' . filemtime(__FILE__) . '|' . filemtime(self::compilerFile())) . '.php';
        if (!is_file($compiled) || filemtime($compiled) < $modified) {
            if (!is_dir($directory)) {
                @mkdir($directory, 0775, true);
            }
            $temporary = $compiled . '.' . bin2hex(random_bytes(6)) . '.tmp';
            if (file_put_contents($temporary, self::compile((string) file_get_contents($file), $file)) === false || !rename($temporary, $compiled)) {
                // @codeCoverageIgnoreStart
                @unlink($temporary);
                throw new RuntimeException("Cannot write the compiled view {$compiled}.");
                // @codeCoverageIgnoreEnd
            }
        }
        return [$compiled, ''];
    }

    private static function compilerFile(): string
    {
        return (string) (new \ReflectionClass(Compiler::class))->getFileName();
    }

    /**
     * @internal A template as the PHP that runs: its directives as PHP (Compiler), every `<?= expr ?>` as
     * `<?= View::escape(expr) ?>`, `__FILE__`/`__DIR__` naming the template itself, and on its first line the
     * imports of its `@use` and of `classes()`, then $first. Every line stays where it is. A template with an
     * error in its syntax throws an InvalidArgumentException with the line.
     */
    public static function compile(string $source, string $file, string $first = ''): string
    {
        $compiler = new Compiler($source);
        foreach ($compiler->problems as $problem) {
            if ($problem['severity'] === 'error') {
                $error = new InvalidArgumentException("View '$file', line {$problem['line']}: {$problem['message']}");
                self::$origins ??= new WeakMap();
                self::$origins[$error] = [$file, $problem['line']];
                throw $error;
            }
        }
        return '<?php ' . Compiler::header('', self::imports($compiler->uses)) . $first . '?>' . self::escapeEchoes($compiler->compile(), $file);
    }

    /**
     * @internal The imports of a view: its `@use`, and `classes()`.
     *
     * @param array<string, string> $uses
     * @return list<array{string, string, string}>
     */
    public static function imports(array $uses): array
    {
        $imports = [['function', 'Waypoint\\classes', 'classes']];
        foreach ($uses as $alias => $name) {
            $imports[] = ['class', $name, $alias];
        }
        return $imports;
    }

    /** Every `<?= expr ?>` as `<?= View::escape(expr) ?>`, `__FILE__` and `__DIR__` as the template's. */
    private static function escapeEchoes(string $source, string $file): string
    {
        $code = '';
        $open = false;
        foreach (token_get_all($source) as $token) {
            $id = is_array($token) ? $token[0] : null;
            $text = is_array($token) ? $token[1] : $token;
            if ($id === T_OPEN_TAG_WITH_ECHO) {
                $code .= $text . '\\' . self::class . '::escape(';
                $open = true;
            } elseif ($open && $id === T_CLOSE_TAG) {
                $code = self::closeEscape($code) . $text;
                $open = false;
            } elseif ($id === T_FILE) {
                $code .= var_export($file, true);
            } elseif ($id === T_DIR) {
                $code .= var_export(dirname($file), true);
            } else {
                $code .= $text;
            }
        }
        return $open ? self::closeEscape($code) : $code;
    }

    /** Ends the escape( call before the whitespace that precedes `?>`, dropping a `;` the template ended the echo with. */
    private static function closeEscape(string $code): string
    {
        return (string) preg_replace('/;?(\s*)\z/', ')$1', $code, 1);
    }

    /**
     * What `<?= ?>` prints: scalars and Stringable HTML-escaped, Html as it is, null as nothing. Anything else (an
     * array, an object without __toString()) is a TypeError rather than "Array".
     */
    public static function escape(mixed ...$values): string
    {
        $html = '';
        foreach ($values as $value) {
            if (!is_scalar($value) && $value !== null && !$value instanceof Stringable) {
                throw new TypeError('A view can only print scalars and Stringable, not ' . get_debug_type($value));
            }
            $html .= $value instanceof Html ? (string) $value : htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE);
        }
        return $html;
    }
}
