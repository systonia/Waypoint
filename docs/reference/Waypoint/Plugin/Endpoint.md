---
title: "Endpoint"
description: "Answers a request before routing (after public files and view assets), like the OpenAPI endpoint."
order: 100
generated: true
---

`Waypoint\Plugin\Endpoint` · interface · namespace [`Waypoint\Plugin`](index.md)

```php
interface Endpoint
```

Answers a request before routing (after public files and view assets), like
the OpenAPI endpoint. No route attributes, CSRF or auth apply: use it for
things deliberately outside the app's routes; everything else is a controller.

## Methods

### serve()

```php
public function serve(
    string $method,
    string $path,
    \Waypoint\Http\Request $req,
    \Waypoint\Http\Response $res,
): bool
```

True if this endpoint handled the request and wrote the response.

**Parameters**

- `$method` `string`
- `$path` `string`
- `$req` [`Request`](../Http/Request.md)
- `$res` [`Response`](../Http/Response.md)

**Returns** `bool`
