<?php

namespace Waypoint\Support;

use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

/**
 * @internal The element type of an `array` property or return type, read from its docblock: `@var list<Item>`,
 * `@var Item[]`, `@var array<string, Item>`, `@return list<Item>`. A class name is resolved the way PHP would
 * resolve it in that file: fully qualified, through its `use` imports, or relative to its namespace.
 */
final class DocType
{
    private const SCALARS = ['int', 'integer', 'float', 'double', 'string', 'bool', 'boolean'];

    /** @var array<string, array<string, string>> file => alias => class */
    private static array $imports = [];

    /**
     * ['list' or 'map', element type] for an array member, or null when its docblock names none. The element type
     * is a scalar keyword (int, string, ...) or an existing class name.
     * @return array{0: 'list'|'map', 1: string}|null
     */
    public static function element(ReflectionProperty|ReflectionMethod $member): ?array
    {
        $doc = $member->getDocComment();
        $tag = $member instanceof ReflectionProperty ? 'var' : 'return';
        if ($doc === false || preg_match('/@' . $tag . '\s+([^\n*]+)/', $doc, $match) !== 1) {
            return null;
        }
        $type = $match[1];
        if (preg_match('/^\??(?:non-empty-)?(?:list|array|iterable)<\s*(?:(int|string|array-key)\s*,\s*)?(\\\\?[\w\\\\]+)\s*>/', $type, $generic) === 1) {
            $kind = $generic[1] === 'string' ? 'map' : 'list';
            $name = $generic[2];
        } elseif (preg_match('/^\??(\\\\?[\w\\\\]+)\[\]/', $type, $suffixed) === 1) {
            $kind = 'list';
            $name = $suffixed[1];
        } else {
            return null;
        }
        $resolved = self::resolve(ltrim($name, '\\'), str_starts_with($name, '\\'), $member->getDeclaringClass());
        return $resolved !== null ? [$kind, $resolved] : null;
    }

    /** @param ReflectionClass<object> $context */
    private static function resolve(string $name, bool $qualified, ReflectionClass $context): ?string
    {
        if (in_array(strtolower($name), self::SCALARS, true)) {
            return strtolower($name);
        }
        if ($qualified) {
            return self::exists($name) ? $name : null;
        }
        $first = explode('\\', $name, 2);
        $imports = self::imports($context);
        $candidates = [
            isset($imports[strtolower($first[0])]) ? $imports[strtolower($first[0])] . (isset($first[1]) ? '\\' . $first[1] : '') : null,
            $context->getNamespaceName() !== '' ? $context->getNamespaceName() . '\\' . $name : null,
            $name,
        ];
        foreach ($candidates as $candidate) {
            if ($candidate !== null && self::exists($candidate)) {
                return $candidate;
            }
        }
        return null;
    }

    private static function exists(string $class): bool
    {
        return class_exists($class) || enum_exists($class) || interface_exists($class);
    }

    /**
     * The `use` imports of the file $context is declared in: lowercased alias => class.
     * @param ReflectionClass<object> $context
     * @return array<string, string>
     */
    private static function imports(ReflectionClass $context): array
    {
        $file = $context->getFileName();
        if ($file === false) {
            return [];
        }
        if (isset(self::$imports[$file])) {
            return self::$imports[$file];
        }
        $imports = [];
        $source = (string) @file_get_contents($file);
        preg_match_all('/^\s*use\s+([\w\\\\]+)\s*(?:\{([^}]*)\}|as\s+(\w+))?\s*;/mi', $source, $uses, PREG_SET_ORDER);
        foreach ($uses as $use) {
            $prefix = trim($use[1], '\\');
            $group = $use[2] ?? '';
            $entries = $group !== '' ? array_map(static fn (string $entry): string => rtrim($prefix, '\\') . '\\' . trim($entry), explode(',', $group)) : [$prefix . (($use[3] ?? '') !== '' ? ' as ' . $use[3] : '')];
            foreach ($entries as $entry) {
                $parts = preg_split('/\s+as\s+/i', trim($entry)) ?: [$entry];
                $class = trim($parts[0], '\\ ');
                if ($class === '' || str_ends_with($class, '\\')) {
                    continue;
                }
                $alias = $parts[1] ?? substr((string) strrchr('\\' . $class, '\\'), 1);
                $imports[strtolower(trim($alias))] = $class;
            }
        }
        return self::$imports[$file] = $imports;
    }
}
