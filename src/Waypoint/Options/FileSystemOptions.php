<?php

namespace Waypoint\Options;

use RuntimeException;

/** Where the compiled route/DI cache lives and where public static files are served from. Relative paths resolve against the front controller's directory. */
class FileSystemOptions
{
    public ?string $cacheDirectory = null {
        get => $this->cacheDirectory;
        set(?string $value) => $this->cacheDirectory = $value !== null ? $this->buildPath($value) : null;
    }

    public ?string $publicDirectory = null {
        get => $this->publicDirectory;
        set(?string $value) => $this->publicDirectory = $value !== null ? $this->buildPath($value) : null;
    }

    /** URL prefix compiled view/layout CSS/JS is served under; normalized to a leading and no trailing slash. */
    public string $assetsPath = '/assets' {
        get => $this->assetsPath;
        set(string $value) => $this->assetsPath = '/' . trim($value, '/');
    }

    /**
     * True (default): the cache is re-verified against controller/view mtimes on every request.
     * False ("trust mode"): the cache is loaded blindly -- clearing it on deploy is your job.
     */
    public bool $cacheValidate = true;

    private function isAbsolutePath(string $path): bool
    {
        return (isset($path[0]) && ($path[0] === '/' || $path[0] === '\\')) || preg_match('/^[a-zA-Z]:[\/\\\\]/', $path) === 1;
    }

    /** $directory as given if absolute, else under the running script's directory (getcwd() as a last resort); no trailing slash. */
    public function buildPath(string $directory): string
    {
        $clean = rtrim($directory, '/\\');
        if ($this->isAbsolutePath($clean)) {
            return $clean;
        }
        $script = $_SERVER['SCRIPT_FILENAME'] ?? '';
        $resolved = realpath(is_string($script) ? $script : '');
        return ($resolved !== false ? dirname($resolved) : getcwd()) . '/' . $clean;
    }

    public function getCacheDirectory(): string
    {
        return $this->cacheDirectory ?? (sys_get_temp_dir() . '/cache');
    }

    public function getPublicDirectory(): string
    {
        return $this->publicDirectory ?? throw new RuntimeException('FileSystemOptions::$publicDirectory is not configured.');
    }
}
