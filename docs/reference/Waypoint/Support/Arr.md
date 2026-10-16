---
title: "Arr"
description: "Narrowing helpers for values whose shape PHPStan can't know statically: a `require`d cache file, a superglobal, json_decode() output."
order: 100
generated: true
---

`Waypoint\Support\Arr` · final class · namespace [`Waypoint\Support`](index.md)

```php
final class Arr
```

Narrowing helpers for values whose shape PHPStan can't know statically:
a `require`d cache file, a superglobal, json_decode() output. Every real
input already has the expected shape (this codebase wrote it); these just
make that explicit so the type checker can trust it too.

## Methods

### stringKeyed()

```php
public static function stringKeyed(mixed $value): array
```

**Parameters**

- `$value` `mixed`

**Returns** `array<string, mixed>`

### stringMap()

```php
public static function stringMap(mixed $value): array
```

**Parameters**

- `$value` `mixed`

**Returns** `array<string, string>`

### stringList()

```php
public static function stringList(mixed $value): array
```

**Parameters**

- `$value` `mixed`

**Returns** `list<string>`

### listOfStringKeyed()

```php
public static function listOfStringKeyed(mixed $value): array
```

**Parameters**

- `$value` `mixed`

**Returns** `list<array<string, mixed>>`
