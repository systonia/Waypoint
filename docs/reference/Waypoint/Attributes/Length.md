---
title: "Length"
description: "Validation: a string property's length (mb_strlen) must be within [$min, $max]."
order: 100
generated: true
---

`Waypoint\Attributes\Length` · class · namespace [`Waypoint\Attributes`](index.md)

```php
class Length
```

Validation: a string property's length (mb_strlen) must be within [$min, $max].

## Properties

### $min

```php
public int $min = 0;
```

### $max

```php
public int $max = 9223372036854775807;
```

## Methods

### __construct()

```php
public function __construct(public int $min = 0, public int $max = \Waypoint\Attributes\PHP_INT_MAX)
```

**Parameters**

- `$min` `int`
- `$max` `int`
