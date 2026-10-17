---
title: "ListOf"
description: "The element type of an `array` property: a class, an enum or a scalar keyword (int, string, float, bool), as a list, or with $map as values by string key."
order: 100
generated: true
---

`Waypoint\Attributes\ListOf` · class · namespace [`Waypoint\Attributes`](index.md)

```php
class ListOf
```

The element type of an `array` property: a class, an enum or a scalar keyword (int, string, float, bool), as a
list, or with $map as values by string key. FromArray builds a DTO or a backed enum case from each element, and
OpenAPI documents the array's items (or additionalProperties for a map). On a promoted constructor parameter it
applies to the property.

## Properties

### $type

```php
public string $type;
```

### $map

```php
public bool $map = false;
```

## Methods

### __construct()

```php
public function __construct(public string $type, public bool $map = false)
```

**Parameters**

- `$type` `string`
- `$map` `bool`
