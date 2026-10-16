---
title: "Controller"
description: "Marks a class as an HTTP controller, with an optional path prefix for every route on it (e.g."
order: 100
generated: true
---

`Waypoint\Attributes\Controller` · class · namespace [`Waypoint\Attributes`](index.md)

```php
class Controller
```

Marks a class as an HTTP controller, with an optional path prefix for every route on it (e.g. '/users').

## Methods

### __construct()

```php
public function __construct(?string $path = null)
```

**Parameters**

- `$path` `?string`

### getPath()

```php
public function getPath(): string
```

'/prefix' or '' when none.

**Returns** `string`
