---
title: "HttpRoute"
description: "Shared body of #[Get]/#[Post]/#[Put]/#[Patch]/#[Delete]: a path pattern such as '/users/{id}'."
order: 100
generated: true
---

`Waypoint\Attributes\HttpRoute` · abstract class · namespace [`Waypoint\Attributes`](index.md)

```php
abstract class HttpRoute implements \Waypoint\Attributes\RouteAttribute
```

Implements [`RouteAttribute`](RouteAttribute.md)

Shared body of #[Get]/#[Post]/#[Put]/#[Patch]/#[Delete]: a path pattern such as '/users/{id}'.

## Methods

### __construct()

```php
public function __construct(private string $path = '')
```

**Parameters**

- `$path` `string`

### getPath()

```php
public function getPath(): string
```

**Returns** `string`
