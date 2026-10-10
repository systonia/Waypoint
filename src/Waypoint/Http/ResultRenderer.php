<?php

namespace Waypoint\Http;

use SplFileInfo;
use Waypoint\Attributes\{FileFormatter, SimpleXmlFormatter};

/** Writes a route's return value to the Response as JSON (default), a raw file/body (#[FileFormatter]) or flat XML (#[SimpleXmlFormatter]). Views and Redirects never come here. */
final class ResultRenderer
{
    /** @param array{type?: string, options?: array<string, mixed>|null} $formatter */
    public function render(mixed $result, Response $res, array $formatter): void
    {
        $type = $formatter['type'] ?? 'json';
        if ($type === FileFormatter::class || $type === 'file') {
            $this->renderFile($result, $res, $formatter['options'] ?? []);
        } elseif ($type === SimpleXmlFormatter::class || $type === 'xml') {
            $this->renderXml($result, $res);
        } else {
            $res->withHeader('Content-Type', 'application/json')->write(self::json($result));
        }
    }

    /**
     * An SplFileInfo is read from disk; a string or other scalar is the content itself, never a path; anything else goes as JSON.
     * A string that happens to name a file (user input, say) is therefore sent as that text, not as the file.
     * @param array<string, mixed> $options
     */
    private function renderFile(mixed $result, Response $res, array $options): void
    {
        $mimetype = $options['mimetype'] ?? null;
        $res->withHeader('Content-Type', is_string($mimetype) ? $mimetype : 'application/octet-stream');

        if (!empty($options['download'])) {
            $filename = $options['filename'] ?? ($result instanceof SplFileInfo ? $result->getFilename() : null);
            // Quotes and line breaks would end the header value
            $filename = is_string($filename) ? str_replace(['"', '\\', "\r", "\n"], '', $filename) : 'download.bin';
            $res->withHeader('Content-Disposition', "attachment; filename=\"$filename\"");
        }

        if ($result instanceof SplFileInfo) {
            $path = $result->getPathname();
            $res->write(is_file($path) ? (@file_get_contents($path) ?: '') : '');
        } elseif (is_scalar($result)) {
            $res->write((string) $result);
        } else {
            $res->write(self::json($result));
        }
    }

    private function renderXml(mixed $result, Response $res): void
    {
        $res->withHeader('Content-Type', 'application/xml');
        $xml = simplexml_load_string('<root/>');
        if ($xml === false) {
            // @codeCoverageIgnoreStart
            // a fixed literal can't fail to parse.
            $res->write('<root/>');
            return;
            // @codeCoverageIgnoreEnd
        }
        $values = is_array($result) ? $result : (array) $result;
        array_walk_recursive($values, function (mixed $v, int|string $k) use ($xml): void {
            $xml->addChild((string) $k, is_scalar($v) ? (string) $v : null);
        });
        // @codeCoverageIgnoreStart
        // asXML() on a document we just built can't fail.
        $res->write($xml->asXML() ?: '<root/>');
        // @codeCoverageIgnoreEnd
    }

    private static function json(mixed $value): string
    {
        $encoded = json_encode($value);
        return $encoded !== false ? $encoded : 'null';
    }
}
