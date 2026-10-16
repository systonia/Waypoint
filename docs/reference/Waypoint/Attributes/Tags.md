---
title: "Tags"
description: "OpenAPI tags for a controller (every route on it) or one route method; both levels are merged."
order: 100
generated: true
---

`Waypoint\Attributes\Tags` · class · namespace [`Waypoint\Attributes`](index.md)

```php
class Tags
```

OpenAPI tags for a controller (every route on it) or one route method; both levels are merged.

## Properties

### $tags

```php
public array $tags = [];
```

**Type** `string[]`

### $name

```php
public ?string $name = null;
```

## Methods

### __construct()

```php
public function __construct(public array $tags = [], public ?string $name = null)
```

**Parameters**

- `$tags` `string[]`
- `$name` `?string`
