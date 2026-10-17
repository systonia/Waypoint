---
title: "Row"
description: "A database row or decoded JSON, read with typed getters instead of casts."
order: 100
generated: true
---

`Waypoint\Support\Row` · final readonly class · namespace [`Waypoint\Support`](index.md)

```php
final readonly class Row
```

A database row or decoded JSON, read with typed getters instead of casts. A missing key or a value of another
type throws an UnexpectedValueException naming the key and the type found: a broken row is a program error, not
a validation error. The nullable getters return null for a null value and for a missing key.

Values are not converted, with one exception: float() also takes an int and a numeric string, since Postgres
sends a `numeric` that a float cannot hold exactly as text.

## Methods

### __construct()

```php
public function __construct(private readonly array $values)
```

**Parameters**

- `$values` `array<mixed>`

### of()

```php
public static function of(mixed $value): \Waypoint\Support\Row
```

Decoded JSON as a Row; throws when it is no array.

**Parameters**

- `$value` `mixed`

**Returns** [`Row`](Row.md)

### each()

```php
public static function each(mixed $value): array
```

Decoded JSON that is a list of objects, one Row each; throws when it is no list of arrays.

**Parameters**

- `$value` `mixed`

**Returns** `list<self>`

### has()

```php
public function has(string $key): bool
```

**Parameters**

- `$key` `string`

**Returns** `bool`

### int()

```php
public function int(string $key): int
```

**Parameters**

- `$key` `string`

**Returns** `int`

### string()

```php
public function string(string $key): string
```

**Parameters**

- `$key` `string`

**Returns** `string`

### float()

```php
public function float(string $key): float
```

**Parameters**

- `$key` `string`

**Returns** `float`

### bool()

```php
public function bool(string $key): bool
```

**Parameters**

- `$key` `string`

**Returns** `bool`

### enum()

```php
public function enum(string $key, string $enum): \BackedEnum
```

The case of $enum the value names.

**Parameters**

- `$key` `string`
- `$enum` `class-string<T>`

**Returns** `T`

**Template parameters**

- `T` of [`BackedEnum`](https://www.php.net/manual/en/class.backedenum.php)

### nullableInt()

```php
public function nullableInt(string $key): ?int
```

**Parameters**

- `$key` `string`

**Returns** `?int`

### nullableString()

```php
public function nullableString(string $key): ?string
```

**Parameters**

- `$key` `string`

**Returns** `?string`

### nullableFloat()

```php
public function nullableFloat(string $key): ?float
```

**Parameters**

- `$key` `string`

**Returns** `?float`

### nullableBool()

```php
public function nullableBool(string $key): ?bool
```

**Parameters**

- `$key` `string`

**Returns** `?bool`

### nullableEnum()

```php
public function nullableEnum(string $key, string $enum): ?\BackedEnum
```

**Parameters**

- `$key` `string`
- `$enum` `class-string<T>`

**Returns** `T|null`

**Template parameters**

- `T` of [`BackedEnum`](https://www.php.net/manual/en/class.backedenum.php)

### row()

```php
public function row(string $key): \Waypoint\Support\Row
```

A nested object as a Row.

**Parameters**

- `$key` `string`

**Returns** [`Row`](Row.md)

### nullableRow()

```php
public function nullableRow(string $key): ?\Waypoint\Support\Row
```

**Parameters**

- `$key` `string`

**Returns** `?`[`Row`](Row.md)

### rows()

```php
public function rows(string $key): array
```

A nested list of objects, one Row each.

**Parameters**

- `$key` `string`

**Returns** `list<self>`

### list()

```php
public function list(string $key, string $type): array
```

A nested list of $type values ('int', 'string', 'float' or 'bool'), each read as its getter does.

**Parameters**

- `$key` `string`
- `$type` `T`

**Returns** `(T is 'int' ? list<int> : (T is 'string' ? list<string> : (T is 'float' ? list<float> : list<bool>)))`

**Template parameters**

- `T` of `'int'|'string'|'float'|'bool'`

### map()

```php
public function map(string $key, string $type): array
```

A nested object of $type values by string key ('int', 'string', 'float' or 'bool'), each read as its getter
does.

**Parameters**

- `$key` `string`
- `$type` `T`

**Returns** `(T is 'int' ? array<string, int> : (T is 'string' ? array<string, string> : (T is 'float' ? array<string, float> : array<string, bool>)))`

**Template parameters**

- `T` of `'int'|'string'|'float'|'bool'`
