---
title: "ResponseHook"
description: "Runs after the controller's result was rendered and before the app-level middlewares unwind."
order: 100
generated: true
---

`Waypoint\Plugin\ResponseHook` · interface · namespace [`Waypoint\Plugin`](index.md)

```php
interface ResponseHook
```

Runs after the controller's result was rendered and before the app-level middlewares unwind. Not when a guard or the
controller threw: the exception handler writes that response later, so the hook would only see a half-finished one.

## Methods

### after()

```php
public function after(array $plan, \Waypoint\Http\Request $req, \Waypoint\Http\Response $res): void
```

**Parameters**

- `$plan` `array<string, mixed>`: What this plugin's RouteAttributeCompiler stored for the route ([] if nothing).
- `$req` [`Request`](../Http/Request.md)
- `$res` [`Response`](../Http/Response.md)
