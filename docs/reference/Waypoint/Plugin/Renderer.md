---
title: "Renderer"
description: "Renders a plugin's own return type."
order: 100
generated: true
---

`Waypoint\Plugin\Renderer` · interface · namespace [`Waypoint\Plugin`](index.md)

```php
interface Renderer
```

Renders a plugin's own return type. Asked after View and Redirect, before the core JSON/XML/file renderer.

## Methods

### supports()

```php
public function supports(mixed $result): bool
```

**Parameters**

- `$result` `mixed`

**Returns** `bool`

### render()

```php
public function render(mixed $result, \Waypoint\Http\Request $req, \Waypoint\Http\Response $res): void
```

**Parameters**

- `$result` `mixed`
- `$req` [`Request`](../Http/Request.md)
- `$res` [`Response`](../Http/Response.md)
