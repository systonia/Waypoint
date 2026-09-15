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
        $res->withHeader('Content-Type', 'application/json')
            ->write(json_encode($result))
            ->send();
    }

    private function renderFileResult($result, Response $res, array $options): void
    {
        $mimetype = $options['mimetype'] ?? 'application/octet-stream';
        $res->withHeader('Content-Type', $mimetype);

        if (!empty($options['download'])) {
            $filename = $options['filename'] ?? (is_string($result) ? basename($result) : 'download.bin');
            $res->withHeader('Content-Disposition', "attachment; filename=\"$filename\"");
        }

        if (is_string($result) && is_file($result)) {
            $res->write(file_get_contents($result))->send();
        } else {
            $res->write(is_scalar($result) ? $result : json_encode($result))->send();
        }
    }

    private function renderXmlResult($result, Response $res): void
    {
        $res->withHeader('Content-Type', 'application/xml');
        $xml = simplexml_load_string('<root/>');
        $arrayResult = is_array($result) ? $result : (array) $result;
        array_walk_recursive($arrayResult, function ($v, $k) use ($xml) {
            $xml->addChild($k, $v);
        });
        $res->write($xml->asXML())->send();
    }
}
