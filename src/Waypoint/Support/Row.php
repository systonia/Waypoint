<?php

namespace Waypoint\Support;

use BackedEnum;
use UnexpectedValueException;

/**
 * A database row or decoded JSON, read with typed getters instead of casts. A missing key or a value of another
 * type throws an UnexpectedValueException naming the key and the type found: a broken row is a program error, not
 * a validation error. The nullable getters return null for a null value and for a missing key.
 *
 * Values are not converted, with one exception: float() also takes an int and a numeric string, since Postgres
 * sends a `numeric` that a float cannot hold exactly as text.
 */
final readonly class Row
{
    /** @param array<mixed> $values */
    public function __construct(private array $values)
    {
    }

    /** Decoded JSON as a Row; throws when it is no array. */
    public static function of(mixed $value): self
    {
        return is_array($value) ? new self($value) : throw new UnexpectedValueException('Expected an array for a row, ' . get_debug_type($value) . ' found.');
    }

    /**
     * Decoded JSON that is a list of objects, one Row each; throws when it is no list of arrays.
     *
     * @return list<self>
     */
    public static function each(mixed $value): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw new UnexpectedValueException('Expected a list of rows, ' . get_debug_type($value) . ' found.');
        }
        return array_map(self::of(...), $value);
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->values);
    }

    public function int(string $key): int
    {
        $value = $this->value($key);
        return is_int($value) ? $value : throw self::wrong($key, 'an int', $value);
    }

    public function string(string $key): string
    {
        $value = $this->value($key);
        return is_string($value) ? $value : throw self::wrong($key, 'a string', $value);
    }

    public function float(string $key): float
    {
        $value = $this->value($key);
        return is_float($value) || is_int($value) || (is_string($value) && is_numeric($value)) ? (float) $value : throw self::wrong($key, 'a number', $value);
    }

    public function bool(string $key): bool
    {
        $value = $this->value($key);
        return is_bool($value) ? $value : throw self::wrong($key, 'a bool', $value);
    }

    /**
     * The case of $enum the value names.
     *
     * @template T of BackedEnum
     * @param class-string<T> $enum
     * @return T
     */
    public function enum(string $key, string $enum): BackedEnum
    {
        $value = $this->value($key);
        return (is_int($value) || is_string($value) ? $enum::tryFrom($value) : null) ?? throw self::wrong($key, "a case of $enum", $value);
    }

    public function nullableInt(string $key): ?int
    {
        return $this->isNull($key) ? null : $this->int($key);
    }

    public function nullableString(string $key): ?string
    {
        return $this->isNull($key) ? null : $this->string($key);
    }

    public function nullableFloat(string $key): ?float
    {
        return $this->isNull($key) ? null : $this->float($key);
    }

    public function nullableBool(string $key): ?bool
    {
        return $this->isNull($key) ? null : $this->bool($key);
    }

    /**
     * @template T of BackedEnum
     * @param class-string<T> $enum
     * @return T|null
     */
    public function nullableEnum(string $key, string $enum): ?BackedEnum
    {
        return $this->isNull($key) ? null : $this->enum($key, $enum);
    }

    /** A nested object as a Row. */
    public function row(string $key): self
    {
        $value = $this->value($key);
        return is_array($value) ? new self($value) : throw self::wrong($key, 'an array', $value);
    }

    public function nullableRow(string $key): ?self
    {
        return $this->isNull($key) ? null : $this->row($key);
    }

    /**
     * A nested list of objects, one Row each.
     *
     * @return list<self>
     */
    public function rows(string $key): array
    {
        $value = $this->value($key);
        if (!is_array($value) || !array_is_list($value)) {
            throw self::wrong($key, 'a list', $value);
        }
        $rows = [];
        foreach ($value as $index => $item) {
            $rows[] = is_array($item) ? new self($item) : throw self::wrong("$key.$index", 'an array', $item);
        }
        return $rows;
    }

    /**
     * A nested list of $type values ('int', 'string', 'float' or 'bool'), each read as its getter does.
     *
     * @template T of 'int'|'string'|'float'|'bool'
     * @param T $type
     * @return (T is 'int' ? list<int> : (T is 'string' ? list<string> : (T is 'float' ? list<float> : list<bool>)))
     */
    public function list(string $key, string $type): array
    {
        $value = $this->value($key);
        if (!is_array($value) || !array_is_list($value)) {
            throw self::wrong($key, 'a list', $value);
        }
        $items = new self($value);
        return array_map(fn (int $index) => $items->$type((string) $index), array_keys($value));
    }

    /**
     * A nested object of $type values by string key ('int', 'string', 'float' or 'bool'), each read as its getter
     * does.
     *
     * @template T of 'int'|'string'|'float'|'bool'
     * @param T $type
     * @return (T is 'int' ? array<string, int> : (T is 'string' ? array<string, string> : (T is 'float' ? array<string, float> : array<string, bool>)))
     */
    public function map(string $key, string $type): array
    {
        $value = $this->value($key);
        if (!is_array($value) || array_is_list($value) && $value !== []) {
            throw self::wrong($key, 'an object', $value);
        }
        $items = new self($value);
        $map = [];
        foreach (array_keys($value) as $name) {
            $map[(string) $name] = $items->$type((string) $name);
        }
        return $map;
    }

    private function value(string $key): mixed
    {
        return array_key_exists($key, $this->values) ? $this->values[$key] : throw new UnexpectedValueException("Row has no value '$key'.");
    }

    private function isNull(string $key): bool
    {
        return ($this->values[$key] ?? null) === null;
    }

    private static function wrong(string $key, string $expected, mixed $value): UnexpectedValueException
    {
        return new UnexpectedValueException("Row value '$key' is not $expected, " . get_debug_type($value) . ' found.');
    }
}
