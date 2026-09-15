<?php

namespace Waypoint\Http;

use Waypoint\Attributes\{FileFormatter, SimpleXmlFormatter};

/**
 * Turns a route handler's return value into an HTTP response body, for
 * every result type except View. A View return is handled by Router itself
 * (Router::renderView()) instead of coming through here, since that needs
 * its own compiled CSS/JS asset lookups and #[Inject] wiring that this
 * class has no business knowing about. Stateless -- everything render()
 * needs comes in through its own parameters.
 */
final class ResultRenderer
{
    /** @param array{type?: string, options?: array<string, mixed>|null} $formatter */
    public function render(mixed $result, Response $res, array $formatter): void
    {
        $type = $formatter['type'] ?? 'json';
        $options = $formatter['options'] ?? [];

        // File Formatter
        if ($type === FileFormatter::class || $type === 'file') {
            $this->renderFileResult($result, $res, $options);
            return;
        }

        // XML Formatter
        if ($type === SimpleXmlFormatter::class || $type === 'xml') {
            $this->renderXmlResult($result, $res);
            return;
        }

        // JSON (default)
        $encoded = json_encode($result);
        $res->withHeader('Content-Type', 'application/json')
            ->write($encoded !== false ? $encoded : 'null')
            ->send();
    }

    /** @param array<string, mixed> $options */
    private function renderFileResult(mixed $result, Response $res, array $options): void
    {
        $mimetype = $options['mimetype'] ?? 'application/octet-stream';
        $res->withHeader('Content-Type', is_string($mimetype) ? $mimetype : 'application/octet-stream');

        if (!empty($options['download'])) {
            $filename = $options['filename'] ?? (is_string($result) ? basename($result) : 'download.bin');
            $filename = is_string($filename) ? $filename : 'download.bin';
            $res->withHeader('Content-Disposition', "attachment; filename=\"$filename\"");
        }

        if (is_string($result) && is_file($result)) {
            $content = @file_get_contents($result);
            // @codeCoverageIgnoreStart
            // Only reachable via a race (deleted/permissions changed
            // between is_file() above and file_get_contents()) that can't
            // be reliably reproduced cross platform -- same guard as
            // FileSystem::readViewAssetFile().
            if ($content === false) {
                $content = '';
            }
            // @codeCoverageIgnoreEnd
            $res->write($content)->send();
        } elseif (is_string($result) || is_int($result) || is_float($result) || is_bool($result)) {
            $res->write((string) $result)->send();
        } else {
            $encoded = json_encode($result);
            $res->write($encoded !== false ? $encoded : 'null')->send();
        }
    }

    private function renderXmlResult(mixed $result, Response $res): void
    {
        $res->withHeader('Content-Type', 'application/xml');
        $xml = simplexml_load_string('<root/>');
        // @codeCoverageIgnoreStart
        // Can't actually fail for this fixed, well-formed literal --
        // simplexml_load_string() is just typed to allow failure for
        // arbitrary/untrusted XML input in general.
        if ($xml === false) {
            $res->write('<root/>')->send();
            return;
        }
        // @codeCoverageIgnoreEnd
        $arrayResult = is_array($result) ? $result : (array) $result;
        array_walk_recursive($arrayResult, function (mixed $v, int|string $k) use ($xml): void {
            $xml->addChild((string) $k, is_scalar($v) ? (string) $v : null);
        });
        $content = $xml->asXML();
        // @codeCoverageIgnoreStart
        // asXML() on a document we ourselves just built from a valid root
        // element can't realistically fail.
        if ($content === false) {
            $content = '<root/>';
        }
        // @codeCoverageIgnoreEnd
        $res->write($content)->send();
    }
}
