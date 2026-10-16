---
title: "Regex"
description: "Validation: the value must match $pattern (a full PCRE pattern including delimiters)."
order: 100
generated: true
---

`Waypoint\Attributes\Regex` · class · namespace [`Waypoint\Attributes`](index.md)

```php
class Regex
```

Validation: the value must match $pattern (a full PCRE pattern including delimiters). Numbers match by their text, null passes, lists, objects and bools never match.

## Properties

### $pattern

```php
public string $pattern;
```

## Methods

### __construct()

```php
public function __construct(public string $pattern)
```

**Parameters**

- `$pattern` `string`
