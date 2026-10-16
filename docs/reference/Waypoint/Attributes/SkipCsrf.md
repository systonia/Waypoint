---
title: "SkipCsrf"
description: "Opts a controller class, or one specific route method, out of CSRF verification (see Waypoint\\Csrf/Router::dispatch()) -- e.g."
order: 100
generated: true
---

`Waypoint\Attributes\SkipCsrf` · class · namespace [`Waypoint\Attributes`](index.md)

```php
class SkipCsrf
```

Opts a controller class, or one specific route method, out of CSRF
verification (see Waypoint\Csrf/Router::dispatch()) -- e.g. a token-
auth-only JSON API with no HTML forms behind it, where a double-submit
cookie makes no sense in the first place. Present on either the
controller class or the method is enough to skip it for that route;
RouteCompiler bakes the combined result into the compiled route plan,
the same way #[NoGzip] does, so dispatch never has to re-check either
at request time.
