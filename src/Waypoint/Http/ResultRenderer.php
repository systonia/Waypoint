<?php

namespace Waypoint\Http;

use SimpleXMLElement;
use SplFileInfo;
use Waypoint\Attributes\{FileFormatter, Ignore, SimpleXmlFormatter};

/** Writes a route's return value to the Response as JSON (default), a raw file/body (#[FileFormatter]) or XML (#[SimpleXmlFormatter]). Views and Redirects never come here. */
final class ResultRenderer
{
    /** @var array<class-string, array<string, true>> class => its #[Ignore] property names */
    private static array $ignored = [];

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
        $xml = new SimpleXMLElement('<root/>');
        $result = self::visible($result);
        self::appendXml($xml, is_object($result) ? get_object_vars($result) : (is_array($result) ? $result : ['value' => $result]));
        // @codeCoverageIgnoreStart
        // asXML() on a document we just built can't fail.
        $res->write($xml->asXML() ?: '<root/>');
        // @codeCoverageIgnoreEnd
    }

    /**
     * Nested arrays and objects become nested elements. A key that is no valid element name (a list index, a space)
     * becomes `<item key="…">`; text is escaped by assigning it, never parsed as markup.
     * @param array<array-key, mixed> $values
     */
    private static function appendXml(SimpleXMLElement $parent, array $values): void
    {
        foreach ($values as $key => $value) {
            $name = (string) $key;
            $validName = preg_match('/^[A-Za-z_][A-Za-z0-9._-]*$/', $name) === 1 && stripos($name, 'xml') !== 0;
            $child = $parent->addChild($validName ? $name : 'item');
            if (!$validName) {
                $child->addAttribute('key', $name);
            }
            if (is_object($value)) {
                $value = get_object_vars($value);
            }
            if (is_array($value)) {
                self::appendXml($child, $value);
            } elseif (is_bool($value)) {
                $child[0] = $value ? 'true' : 'false';
            } elseif (is_scalar($value)) {
                $child[0] = (string) $value;
            }
        }
    }

    /** Throws for what JSON can't hold (invalid UTF-8, say): a logged 500 instead of a 200 with "null". */
    private static function json(mixed $value): string
    {
        // Rows from a database are arrays of scalars: nothing to hide, so they skip the walk
        $plain = is_array($value) && !self::hasObjects($value);
        return json_encode($plain ? $value : self::visible($value), JSON_THROW_ON_ERROR);
    }

    /**
     * Whether an object sits anywhere in $value. A loop over a stack instead of recursion: a function call
     * per nested array would cost as much as the walk it saves. Deeper than visible() goes (an array that
     * holds itself by reference) counts as yes, so visible() stops there as before.
     * @param array<array-key, mixed> $value
     */
    private static function hasObjects(array $value): bool
    {
        $pending = [[$value, 0]];
        while ($pending !== []) {
            [$array, $depth] = array_pop($pending);
            foreach ($array as $item) {
                if (is_object($item)) {
                    return true;
                }
                if (is_array($item)) {
                    if ($depth >= 512) {
                        return true;
                    }
                    $pending[] = [$item, $depth + 1];
                }
            }
        }
        return false;
    }

    /**
     * $value with every object's #[Ignore] properties left out, in nested objects and arrays too: a plain object
     * becomes its public properties as a stdClass. A JsonSerializable, an enum or a date stays as it is.
     */
    private static function visible(mixed $value, int $depth = 0): mixed
    {
        if ($depth > 512) {
            return $value; // json_encode() reports the depth (a cycle, say) as before
        }
        if (is_array($value)) {
            // Only arrays and objects can hold an #[Ignore]: scalars are skipped, so a list of plain rows costs a loop, no calls
            foreach ($value as $key => $item) {
                if (is_array($item) || is_object($item)) {
                    $value[$key] = self::visible($item, $depth + 1);
                }
            }
            return $value;
        }
        if (!is_object($value) || $value instanceof \JsonSerializable || $value instanceof \UnitEnum || $value instanceof \DateTimeInterface) {
            return $value;
        }
        $ignored = self::$ignored[$value::class] ??= self::ignoredOf($value);
        $properties = array_diff_key(get_object_vars($value), $ignored);
        return (object) self::visible($properties, $depth);
    }

    /** @return array<string, true> */
    private static function ignoredOf(object $value): array
    {
        $ignored = [];
        foreach ((new \ReflectionObject($value))->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->getAttributes(Ignore::class) !== []) {
                $ignored[$property->getName()] = true;
            }
        }
        return $ignored;
    }
}
