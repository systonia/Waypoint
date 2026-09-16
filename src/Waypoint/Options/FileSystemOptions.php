<?php

namespace Waypoint\Options;

use RuntimeException;

/**
 * Configures where Waypoint looks for its compiled route/DI cache and serves
 * public static files from. Resolved through the container like any other
 * Options class -- configure it via App::configure():
 *
 *   $app->configure(function (FileSystemOptions $fs) {
 *       $fs->cacheDirectory = __DIR__ . '/cache';
 *       $fs->publicDirectory = __DIR__ . '/public';
 *   });
 */
class FileSystemOptions
{
    /**
     * Undocumented variable
     *
     * @var string|null
     */
    public ?string $cacheDirectory = null {
        get => $this->cacheDirectory;
        set(?string $value) => $this->cacheDirectory = $value !== null
            ? $this->buildPath($value)
            : null;
    }

    /**
     * Undocumented variable
     *
     * @var string|null
     */
    public ?string $publicDirectory = null {
        get => $this->publicDirectory;
        set(?string $value) => $this->publicDirectory = $value !== null
            ? $this->buildPath($value)
            : null;
    }

    /**
     * The URL path prefix view/layout CSS/JS and the bundled waypoint.js
     * client are served under -- e.g. `GET {assetsPath}/{filename}` (see
     * Router::tryServeViewAsset()). Always normalized to a leading slash
     * and no trailing slash, so `"$assetsPath/$filename"` is always safe
     * to build directly.
     *
     * @var string
     */
    public string $assetsPath = '/assets' {
        get => $this->assetsPath;
        set(string $value) => $this->assetsPath = '/' . trim($value, '/');
    }

    /**
     * When true (the default), a cached route/DI compilation is only used
     * after re-verifying it's still fresh -- reflecting every controller
     * and comparing file mtimes, on every single request. Set to false to
     * skip that and just trust the cache blindly instead, the same
     * tradeoff Symfony/Laravel's own prod caching makes: you're
     * responsible for clearing/rebuilding the cache directory on deploy,
     * and nothing re-checks it live.
     *
     * @var bool
     */
    public bool $cacheValidate = true;

    /**
     * Undocumented function
     *
     * @param string $path
     * @return boolean
     */
    private function isAbsolutePath(string $path): bool
    {
        // Unix absolute or Windows absolute (C:\ or D:/)
        return (
            isset($path[0]) && ($path[0] === '/' || $path[0] === '\\') ||
            preg_match('/^[a-zA-Z]:[\/\\\\]/', $path)
        );
    }

    /**
     * Undocumented function
     *
     * @param string $directory
     * @return string
     */
    public function buildPath(string $directory): string {
        $cleanDirectory = rtrim($directory, '/\\');
        if ($this->isAbsolutePath($cleanDirectory)) {
            return rtrim($cleanDirectory, '/\\');
        } else {
            $scriptFilename = $_SERVER['SCRIPT_FILENAME'] ?? null;
            $scriptFilename = is_string($scriptFilename) ? $scriptFilename : '';
            $resolved = realpath($scriptFilename);
            $base = $resolved !== false ? dirname($resolved) : getcwd();
            return $base . '/' . $cleanDirectory;
        }
    }

    /**
     * Undocumented function
     *
     * @return string
     */
    public function getCacheDirectory(): string
    {
        return $this->cacheDirectory ?? (sys_get_temp_dir() . '/cache');
    }

    /**
     * Undocumented function
     *
     * @return string
     */
    public function getPublicDirectory(): string
    {
        return $this->publicDirectory ?? throw new RuntimeException("ToDo: error message");
    }
}
