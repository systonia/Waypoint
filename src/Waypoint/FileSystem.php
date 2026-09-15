<?php

namespace Waypoint;

use ReflectionClass;

use Waypoint\Router;
use Waypoint\Options\FileSystemOptions;

/**
 * Reads/writes the compiled route/DI/attribute cache, and resolves the
 * public static-file directory -- both driven by a FileSystemOptions
 * instance (see App::configure(FileSystemOptions)) rather than any state
 * of its own.
 */
final class FileSystem
{
    /**
     *
     */
    private const ROUTES_FILE = 'routes.php';

    /**
     *
     */
    private const META_FILE = 'meta.php';

    /**
     *
     */
    private const ATTRIBUTES_FILE = 'attributes.php';

    /**
     *
     */
    private const ASSETS_DIR = 'assets';

    public function __construct(private FileSystemOptions $options = new FileSystemOptions())
    {
    }

    public function getRouteFile(): string
    {
        return $this->options->getCacheDirectory() . '/' . self::ROUTES_FILE;
    }

    /**
     * Undocumented function
     *
     * @return string
     */
    public function getMetaFile(): string
    {
        return $this->options->getCacheDirectory() . '/' . self::META_FILE;
    }

    /**
     * Undocumented function
     *
     * @return string
     */
    public function getAttributesFile(): string
    {
        return $this->options->getCacheDirectory() . '/' . self::ATTRIBUTES_FILE;
    }

    /**
     * Where compiled view/layout asset files (ViewAssets::compile()'s
     * content-hashed .css/.js) are written -- a subdirectory rather than
     * routes.php itself, so the compiled route/DI cache stays a small,
     * quick-to-require PHP array instead of embedding every view's full
     * CSS/JS text as string literals in it.
     */
    public function getAssetsDirectory(): string
    {
        return $this->options->getCacheDirectory() . '/' . self::ASSETS_DIR;
    }

    /**
     * Cheap existence-only check for trust mode ($options->cacheValidate =
     * false): a single stat call, versus isAvailable()'s full
     * reflect-and-compare pass over every controller.
     */
    public function hasCachedRoutes(): bool
    {
        return is_file($this->getRouteFile());
    }

    /**
     * Reads routes.php once and returns its full decoded contents
     * (staticRoutes/dynamicRoutes/tasks/services), or null if there's no
     * usable cache yet. Trust mode's single source of truth -- both
     * App::attach() (for 'services') and Router (for the route plans
     * themselves) reuse this one read instead of each `require`-ing the
     * same file independently.
     *
     * @return array<string, mixed>|null
     */
    public function loadCachedRouteData(): ?array
    {
        if (!$this->hasCachedRoutes()) {
            return null;
        }
        $data = @require $this->getRouteFile();
        return is_array($data) ? $data : null;
    }

    /**
     * Reads back the controller/service class list that was compiled into
     * the cached routes.php (stored by storeFromRouter() under 'services'),
     * for trust mode to reuse instead of re-discovering it via Reflection.
     * Returns null if there's no usable cache yet.
     *
     * @return string[]|null
     */
    public function loadCachedServices(): ?array
    {
        $data = $this->loadCachedRouteData();
        return is_array($data['services'] ?? null) ? $data['services'] : null;
    }

    /**
     * @param class-string[] $controllers
     * @param array<string, int> $extraMeta Additional {path => mtime}
     *  entries to require an exact match on too, alongside the controllers'
     *  own -- e.g. ViewAssets::discoverMeta()'s view .css/.js mtimes, so
     *  editing one invalidates the cache the same way editing a controller
     *  does, without isAvailable() needing to know anything about views
     *  itself.
     * @return boolean
     */
    public function isAvailable(array $controllers, array $extraMeta = []): bool
    {
        $routeFile = $this->getRouteFile();
        $metaFile = $this->getMetaFile();
        $attributesFile = $this->getAttributesFile();

        // getRouteFile()/getMetaFile()/getAttributesFile() are always
        // strings (their own return type), so only existence needs
        // checking here.
        foreach ([$routeFile, $metaFile, $attributesFile] as $file) {
            if (!is_file($file)) {
                return false;
            }
        }

        $storedMeta = @require $metaFile;
        if (!is_array($storedMeta)) {
            return false;
        }

        $currentMeta = $extraMeta;
        foreach ($controllers as $controller) {
            if (!class_exists($controller)) {
                continue;
            }

            $rc = new ReflectionClass($controller);
            $file = $rc->getFileName();

            if (!$file || !file_exists($file)) {
                return false;
            }

            $currentMeta[$file] = filemtime($file);
        }

        // Require an exact match, not just that every *current* controller
        // is present: otherwise a cache built for a larger controller set
        // (e.g. one that later had a controller removed) would incorrectly
        // validate for the smaller set and silently keep serving the
        // removed controller's routes/tasks from the stale cache.
        if (count($currentMeta) !== count($storedMeta)) {
            return false;
        }

        foreach ($currentMeta as $file => $mtime) {
            if (!isset($storedMeta[$file]) || $storedMeta[$file] !== $mtime) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param Router $router
     * @param class-string[] $controllers
     * @param class-string[] $serviceClasses
     * @param array<string, int> $extraMeta See isAvailable()'s $extraMeta --
     *  the same view-asset mtimes that decided a rebuild was needed here
     *  get persisted here too, so the next request's isAvailable() call has
     *  something to compare against.
     * @return void
     */
    public function storeFromRouter(Router $router, array $controllers, array $serviceClasses, array $extraMeta = []): void
    {
        $dir = $this->options->getCacheDirectory();
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $data = $router->exportPlans();
        $data['services'] = $serviceClasses;

        $header = "<?php\n// AUTO-GENERATED FILE - DO NOT EDIT\n\n";
        file_put_contents($dir . '/' . self::ROUTES_FILE, $header . 'return ' . var_export($data, true) . ';');

        $meta = $extraMeta;
        foreach ($controllers as $controller) {
            if (!class_exists($controller))
                continue;
            $rc = new ReflectionClass($controller);
            $file = $rc->getFileName();
            if ($file && file_exists($file)) {
                $meta[$file] = filemtime($file);
            }
        }
        file_put_contents($dir . '/' . self::META_FILE, $header . 'return ' . var_export($meta, true) . ';');

        $this->storeAttributes($controllers);
    }

    /**
     * Writes each {filename => {content, mime}} entry (from
     * ViewAssets::compile()) to getAssetsDirectory()/{filename}, and
     * returns the same set stripped down to {filename => mime} -- that's
     * what actually gets persisted into routes.php by storeFromRouter(),
     * so the cache file itself never embeds any view's CSS/JS text.
     *
     * Filenames are content hashes (see ViewAssets::compile()), so they're
     * immutable once written: an existing file is trusted as-is rather
     * than rewritten, and a stale/orphaned file left behind by an edited
     * view is never cleaned up here -- the same "you own clearing/rebuilding
     * this directory" tradeoff trust mode already makes for the rest of
     * this cache.
     *
     * @param array<string, array{content: string, mime: string}> $files
     * @return array<string, string> {filename => mime}
     */
    public function storeViewAssetFiles(array $files): array
    {
        if ($files === []) {
            return [];
        }

        $dir = $this->getAssetsDirectory();
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $stripped = [];
        foreach ($files as $filename => $asset) {
            $path = $dir . '/' . $filename;
            if (!is_file($path)) {
                file_put_contents($path, $asset['content']);
            }
            $stripped[$filename] = $asset['mime'];
        }

        return $stripped;
    }

    /**
     * Reads back one view/layout asset file written by
     * storeViewAssetFiles(), or null if it doesn't exist -- e.g. the cache
     * directory was cleared between storing the {filename => mime}
     * manifest and serving it. $filename is trusted to already be a known
     * key from that manifest (see Router::tryServeViewAsset(), which looks
     * it up there first) -- never a path built from unvalidated request
     * input, so no separate traversal guard is needed here.
     */
    public function readViewAssetFile(string $filename): ?string
    {
        $path = $this->getAssetsDirectory() . '/' . $filename;
        if (!is_file($path)) {
            return null;
        }

        $content = @file_get_contents($path);
        // @codeCoverageIgnoreStart
        // Only reachable via a race (deleted/permissions changed between
        // is_file() and file_get_contents()) that can't be reliably
        // reproduced cross platform, especially under Windows ACLs -- same
        // guard as extendWithEnvFile()/extendWithJsonFile() in
        // EnvironmentOptions.
        if ($content === false) {
            return null;
        }
        // @codeCoverageIgnoreEnd

        return $content;
    }

    /**
     * @param class-string[] $controllers
     * @return void
     */
    public function storeAttributes(array $controllers): void
    {
        $dir = $this->options->getCacheDirectory();
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $attributes = RouteCompiler::exportAllAttributes($controllers);

        $header = "<?php\n// AUTO-GENERATED FILE - DO NOT EDIT\n\n";
        file_put_contents($dir . '/' . self::ATTRIBUTES_FILE, $header . 'return ' . var_export($attributes, true) . ';');
    }

    /**
     * @return array<class-string, mixed> See RouteCompiler::exportAllAttributes() for the shape.
     */
    public function loadAttributes(): array
    {
        $file = $this->getAttributesFile();
        if (!file_exists($file)) {
            return [];
        }

        return require $file;
    }

    /**
     * Undocumented function
     *
     * @param Router $router
     * @return void
     */
    public function loadToRouter(Router $router): void
    {
        $routeFile = $this->getRouteFile();
        if (!file_exists($routeFile)) {
            return;
        }
        $data = require $routeFile;
        $router->importPlans($data);
    }
}
