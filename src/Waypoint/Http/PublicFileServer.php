<?php

namespace Waypoint\Http;

use Waypoint\Options\FileSystemOptions;

/** Serves the app's own static files from FileSystemOptions::$publicDirectory, matched against the request path (realpath-confined to that directory). */
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
        $root = realpath($publicPath);
        $file = realpath($publicPath . $path);
        if (!$file || !$root || !str_starts_with($file, $root) || !is_file($file)) {
            return false;
        }

        $content = @file_get_contents($file);
        if ($content === false) {
            // @codeCoverageIgnoreStart
            // only a delete/permission race after is_file().
            return false;
            // @codeCoverageIgnoreEnd
        }
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $res->withHeader('Content-Type', self::MIME_MAP[$ext] ?? (mime_content_type($file) ?: 'application/octet-stream'))
            ->withHeader('Content-Length', (string) strlen($content))
            ->write($content);
        return true;
    }
}
