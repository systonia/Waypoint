---
title: "Fallback"
description: "Answers a request no route matched, before the 404: pages from a CMS, short links, legacy URLs."
order: 100
generated: true
---

`Waypoint\Plugin\Fallback` · interface · namespace [`Waypoint\Plugin`](index.md)

```php
interface Fallback
```

Answers a request no route matched, before the 404: pages from a CMS, short links, legacy URLs. The app's
routes always come first. No route attributes, CSRF or auth apply, like for an Endpoint; the response hooks
run (with an empty plan), so a hook that rewrites HTML pages sees these too.

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

True if it answered and wrote the response; false lets the next fallback, and finally the 404, have it.

**Parameters**

- `$method` `string`
- `$path` `string`
- `$req` [`Request`](../Http/Request.md)
- `$res` [`Response`](../Http/Response.md)

**Returns** `bool`
