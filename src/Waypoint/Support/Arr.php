<?php

namespace Waypoint\Support;

/**
 * Narrowing helpers for values whose shape PHPStan can't know statically:
 * a `require`d cache file, a superglobal, json_decode() output. Every real
 * input already has the expected shape (this codebase wrote it); these just
 * make that explicit so the type checker can trust it too.
 */
final class Arr
{
    /** @return array<string, mixed> */
    public static function stringKeyed(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }
        $result = [];
        foreach ($value as $key => $item) {
            if (is_string($key)) {
                $result[$key] = $item;
            }
        }
        return $result;
    }

    /** @return array<string, string> */
    public static function stringMap(mixed $value): array
    {
        $result = [];
        foreach (self::stringKeyed($value) as $key => $item) {
            if (is_string($item)) {
                $result[$key] = $item;
            }
        }
        return $result;
    }

    /** @return list<string> */
    public static function stringList(mixed $value): array
    {
        return is_array($value) ? array_values(array_filter($value, 'is_string')) : [];
    }

    /** @return list<array<string, mixed>> */
    public static function listOfStringKeyed(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }
        $result = [];
        foreach ($value as $item) {
            if (is_array($item)) {
                $result[] = self::stringKeyed($item);
            }
        }
        return $result;
    }
}
