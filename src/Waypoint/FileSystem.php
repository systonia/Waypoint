<?php

namespace Waypoint;

use ReflectionClass;
use RuntimeException;
use Waypoint\Options\FileSystemOptions;
use Waypoint\Support\Arr;

/**
 * The on-disk compilation cache under FileSystemOptions::$cacheDirectory:
 * routes.php (plans + service list), meta.php (source mtimes that produced
 * it), attributes.php (RouteCompiler::exportAllAttributes() for OpenAPI),
 * and assets/ (content-hashed view CSS/JS, kept out of routes.php so the
 * `require`d file stays small).
 */
final class FileSystem
{
    private const ROUTES_FILE = 'routes.php';
    private const META_FILE = 'meta.php';
    private const ATTRIBUTES_FILE = 'attributes.php';
    private const ASSETS_DIR = 'assets';

    private static ?int $frameworkMtime = null;

    public function __construct(private FileSystemOptions $options = new FileSystemOptions())
    {
    }

    public function getRouteFile(): string
    {
        return $this->options->getCacheDirectory() . '/' . self::ROUTES_FILE;
    }

    public function getMetaFile(): string
    {
        return $this->options->getCacheDirectory() . '/' . self::META_FILE;
    }

    public function getAttributesFile(): string
    {
        return $this->options->getCacheDirectory() . '/' . self::ATTRIBUTES_FILE;
    }

    public function getAssetsDirectory(): string
    {
        return $this->options->getCacheDirectory() . '/' . self::ASSETS_DIR;
    }

    /** Trust mode's whole check: one stat call. */
    public function hasCachedRoutes(): bool
    {
        return is_file($this->getRouteFile());
    }

    /**
     * The decoded routes.php (plans + 'services'), or null without a cache.
     * @return array<string, mixed>|null
     */
    public function loadCachedRouteData(): ?array
    {
        if (!$this->hasCachedRoutes()) {
            return null;
        }
        $data = @require $this->getRouteFile();
        return is_array($data) ? Arr::stringKeyed($data) : null;
    }

    /**
     * The class list compiled into routes.php, or null without a cache.
     * @return string[]|null
     */
    public function loadCachedServices(): ?array
    {
        $services = $this->loadCachedRouteData()['services'] ?? null;
        return is_array($services) ? Arr::stringList($services) : null;
    }

    /**
     * True if the cache exists and was built from exactly the current sources: every controller
     * file (by mtime) plus $extraMeta (view .css/.js mtimes), no more and no fewer -- a cache built
     * for a larger controller set must not keep serving a removed controller's routes.
     *
     * @param class-string[] $controllers
     * @param array<string, int> $extraMeta path => mtime
     */
    public function isAvailable(array $controllers, array $extraMeta = []): bool
    {
        foreach ([$this->getRouteFile(), $this->getMetaFile(), $this->getAttributesFile()] as $file) {
            if (!is_file($file)) {
                return false;
            }
        }
        $stored = @require $this->getMetaFile();
        if (!is_array($stored)) {
            return false;
        }

        $current = $this->controllerMeta($controllers, $extraMeta);
        if ($current === null || count($current) !== count($stored)) {
            return false;
        }
        foreach ($current as $file => $mtime) {
            if (($stored[$file] ?? null) !== $mtime) {
                return false;
            }
        }
        return true;
    }

    /**
     * Everything the compiled cache depends on: $extraMeta, the framework's own client bundle and sources (an update
     * may change what the cache holds), and the source file mtime of each controller, its base classes and their
     * traits (their attributes are inherited) -- or null if a controller's file is gone.
     * @param class-string[] $controllers
     * @param array<string, int> $extraMeta
     * @return array<string, int>|null
     */
    private function controllerMeta(array $controllers, array $extraMeta): ?array
    {
        $meta = $extraMeta;
        $meta[ViewAssets::WAYPOINT_JS] = filemtime(ViewAssets::WAYPOINT_JS) ?: 0;
        $meta[__DIR__] = self::frameworkMtime();
        // The plans hold what the controllers' #[Middleware] classes inject and which #[Body] DTOs they build too
        $classes = $controllers;
        foreach ($controllers as $controller) {
            if (class_exists($controller)) {
                $classes = [...$classes, ...self::referencedClasses(new ReflectionClass($controller))];
            }
        }
        foreach (array_unique($classes) as $controller) {
            if (!class_exists($controller)) {
                continue;
            }
            for ($class = new ReflectionClass($controller); $class !== false; $class = $class->getParentClass()) {
                // A trait's methods and their attributes become the class's own
                foreach ([$class, ...self::traitsOf($class)] as $source) {
                    $file = $source->getFileName();
                    if ($file === false && $source->getName() !== $controller) {
                        // A built-in parent class has no source file to watch
                        continue;
                    }
                    if (!$file || !file_exists($file)) {
                        return null;
                    }
                    $mtime = filemtime($file);
                    if ($mtime === false) {
                        // @codeCoverageIgnoreStart
                        // only a delete race after file_exists().
                        return null;
                        // @codeCoverageIgnoreEnd
                    }
                    $meta[$file] = $mtime;
                }
            }
        }
        return $meta;
    }

    /**
     * The traits a class uses, and the traits they use in turn.
     * @param ReflectionClass<object> $class
     * @return list<ReflectionClass<object>>
     */
    private static function traitsOf(ReflectionClass $class): array
    {
        $traits = [];
        foreach ($class->getTraits() as $trait) {
            $traits = [...$traits, $trait, ...self::traitsOf($trait)];
        }
        return $traits;
    }

    /**
     * The #[Middleware] classes of a controller and its routes, and the #[Body] DTO classes its routes take.
     * @param ReflectionClass<object> $controller
     * @return list<class-string>
     */
    private static function referencedClasses(ReflectionClass $controller): array
    {
        $found = [];
        $holders = [$controller, ...$controller->getMethods()];
        foreach ($holders as $holder) {
            foreach ($holder->getAttributes(\Waypoint\Attributes\Middleware::class) as $attribute) {
                $class = $attribute->getArguments()[0] ?? $attribute->getArguments()['class'] ?? null;
                if (is_string($class) && class_exists($class)) {
                    $found[] = $class;
                }
            }
        }
        foreach ($controller->getMethods() as $method) {
            foreach ($method->getParameters() as $param) {
                $body = $param->getAttributes(\Waypoint\Attributes\Body::class)[0] ?? null;
                if ($body === null) {
                    continue;
                }
                $type = $param->getType();
                $candidates = [$type instanceof \ReflectionNamedType ? $type->getName() : null, $body->getArguments()['of'] ?? null];
                foreach ($candidates as $class) {
                    if (is_string($class) && class_exists($class)) {
                        $found[] = $class;
                    }
                }
            }
        }
        return $found;
    }

    /** The newest mtime among the framework's own PHP files; once per process, they only change with a deploy. */
    private static function frameworkMtime(): int
    {
        if (self::$frameworkMtime === null) {
            $newest = 0;
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(__DIR__, \FilesystemIterator::SKIP_DOTS));
            foreach ($files as $file) {
                if ($file instanceof \SplFileInfo && $file->getExtension() === 'php') {
                    $newest = max($newest, $file->getMTime() ?: 0);
                }
            }
            self::$frameworkMtime = $newest;
        }
        return self::$frameworkMtime;
    }

    /**
     * Writes routes.php, meta.php and attributes.php from the Router's current plans.
     * @param class-string[] $controllers
     * @param class-string[] $serviceClasses
     * @param array<string, int> $extraMeta The same view-asset mtimes isAvailable() will compare against next time.
     */
    public function storeFromRouter(Router $router, array $controllers, array $serviceClasses, array $extraMeta = []): void
    {
        $data = $router->exportPlans();
        $data['services'] = $serviceClasses;
        $this->writePhpArray(self::ROUTES_FILE, $data);
        $this->writePhpArray(self::META_FILE, $this->controllerMeta($controllers, $extraMeta) ?? $extraMeta);
        $this->storeAttributes($controllers);
    }

    /** @param class-string[] $controllers */
    public function storeAttributes(array $controllers): void
    {
        $this->writePhpArray(self::ATTRIBUTES_FILE, RouteCompiler::exportAllAttributes($controllers));
    }

    /** @return array<class-string, mixed> See RouteCompiler::exportAllAttributes() for the shape. */
    public function loadAttributes(): array
    {
        $file = $this->getAttributesFile();
        $data = file_exists($file) ? require $file : [];
        $result = [];
        foreach (Arr::stringKeyed($data) as $key => $item) {
            if (class_exists($key)) {
                $result[$key] = $item;
            }
        }
        return $result;
    }

    public function loadToRouter(Router $router): void
    {
        $data = $this->loadCachedRouteData();
        if ($data !== null) {
            $router->importPlans($data);
        }
    }

    /**
     * Writes each compiled asset to assets/{filename} and returns {filename => mime} for routes.php.
     * Filenames are content hashes, so an existing file is never rewritten -- and a stale one is
     * never cleaned up here (clearing the cache directory is the deployment's job).
     *
     * @param array<string, array{content: string, mime: string}> $files
     * @return array<string, string>
     */
    public function storeViewAssetFiles(array $files): array
    {
        if ($files === []) {
            return [];
        }
        $dir = $this->getAssetsDirectory();
        self::ensureDirectory($dir);
        $mimes = [];
        foreach ($files as $filename => $asset) {
            if (!is_file("$dir/$filename")) {
                self::writeAtomically("$dir/$filename", $asset['content']);
            }
            $mimes[$filename] = $asset['mime'];
        }
        return $mimes;
    }

    /** One stored asset's content, or null if it's gone (e.g. the cache directory was cleared). $filename is a manifest key, never raw request input. */
    public function readViewAssetFile(string $filename): ?string
    {
        $path = $this->getAssetsDirectory() . '/' . $filename;
        if (!is_file($path)) {
            return null;
        }
        $content = @file_get_contents($path);
        // @codeCoverageIgnoreStart
        // only a delete/permission race between is_file() and the read.
        return $content === false ? null : $content;
        // @codeCoverageIgnoreEnd
    }

    /** @param array<array-key, mixed> $data */
    private function writePhpArray(string $filename, array $data): void
    {
        $dir = $this->options->getCacheDirectory();
        self::ensureDirectory($dir);
        self::writeAtomically("$dir/$filename", "<?php\n// AUTO-GENERATED FILE - DO NOT EDIT\n\nreturn " . var_export($data, true) . ';');
    }

    /** Two processes may create it at the same moment: only a directory that still doesn't exist afterwards is an error. */
    private static function ensureDirectory(string $dir): void
    {
        if (!is_dir($dir) && !@mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new RuntimeException("Cannot create the cache directory {$dir}.");
        }
    }

    /**
     * Writes next to the target and renames over it: a concurrent request reads the old file or the new one, never half
     * of one (a half-written routes.php would be a parse error in someone else's request).
     */
    private static function writeAtomically(string $path, string $content): void
    {
        $temporary = $path . '.' . bin2hex(random_bytes(6)) . '.tmp';
        if (file_put_contents($temporary, $content) === false || !rename($temporary, $path)) {
            @unlink($temporary);
            throw new RuntimeException("Cannot write the cache file {$path}.");
        }
    }
}
