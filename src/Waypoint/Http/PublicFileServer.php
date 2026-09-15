<?php

namespace Waypoint\Http;

use Waypoint\Options\FileSystemOptions;

/**
 * Serves files directly from FileSystemOptions::$publicDirectory (a
 * consumer app's own images/robots.txt/favicon.ico/etc). Distinct from
 * Router::tryServeViewAsset(), which only ever serves what Waypoint itself
 * compiled (view CSS/JS, the bundled waypoint.js client) -- this serves
 * whatever the app happens to have put in its public directory, matched
 * directly against the request path.
 */
final class PublicFileServer
{
    private const MIME_MAP = [
        'css'  => 'text/css; charset=utf-8',
        'js'   => 'application/javascript; charset=utf-8',
        'mjs'  => 'application/javascript; charset=utf-8',
        'json' => 'application/json; charset=utf-8',
        'svg'  => 'image/svg+xml',
        'html' => 'text/html; charset=utf-8',
    ];

    public function __construct(private FileSystemOptions $options)
    {
    }

    public function serve(string $path, Response $res): bool
    {
        if ($this->options->publicDirectory === null) {
            return false;
        }

        $publicPath = $this->options->getPublicDirectory();
        $filePath = realpath($publicPath . $path);

        if (
            !$filePath
            || !str_starts_with($filePath, realpath($publicPath))
            || !is_file($filePath)
        ) {
            return false;
        }

        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $mime = self::MIME_MAP[$ext] ?? (mime_content_type($filePath) ?: 'application/octet-stream');

        $res->withHeader('Content-Type', $mime)
            ->withHeader('Content-Length', (string) filesize($filePath))
            ->write(file_get_contents($filePath))
            ->send();

        return true;
    }
}
