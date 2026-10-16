---
title: "SecurityHeadersMiddleware"
description: "Fills in the standard security headers (SecurityHeaderOptions) in before(), so they are there on an error a later middleware throws too (after() would be skipped then); a controller or middleware setting one of them afterwards replaces it, and one set before is kept."
order: 100
generated: true
---

`Waypoint\Http\SecurityHeadersMiddleware` · class · namespace [`Waypoint\Http`](index.md)

```php
class SecurityHeadersMiddleware extends \Waypoint\Http\MiddlewareBase
```

Extends [`MiddlewareBase`](MiddlewareBase.md)

Fills in the standard security headers (SecurityHeaderOptions) in before(),
so they are there on an error a later middleware throws too (after() would
be skipped then); a controller or middleware setting one of them afterwards
replaces it, and one set before is kept. Register with
`$app->use(new SecurityHeadersMiddleware())`.

## Methods

### before()

```php
protected function before(\Waypoint\Http\Request $req, \Waypoint\Http\Response $res): bool
```

Return false to stop the chain here.

**Parameters**

- `$req` [`Request`](Request.md)
- `$res` [`Response`](Response.md)

**Returns** `bool`
