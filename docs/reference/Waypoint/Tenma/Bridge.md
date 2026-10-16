---
title: "Bridge"
description: "Translates between Tenma (PHP with an event loop) and Waypoint for App::__invoke(), which makes an app Tenma's request handler: `Tenma::serve(3000, $app)`."
order: 100
generated: true
---

`Waypoint\Tenma\Bridge` · final class · namespace [`Waypoint\Tenma`](index.md)

```php
final class Bridge
```

Translates between Tenma (PHP with an event loop) and Waypoint for App::__invoke(), which makes
an app Tenma's request handler: `Tenma::serve(3000, $app)`. One process then serves many requests,
each in a Fiber of its own, interleaved while they wait on I/O. Only used under Tenma; Waypoint has no
dependency on it.

## Methods

### respond()

```php
public static function respond(\Waypoint\App $app, \Tenma\Http\Request $request): \Tenma\Http\Response|\Tenma\Http\WebSocketResponse|\Tenma\Http\StreamedResponse
```

One request: Tenma's Request in, through App::handle(), Tenma's Response out.

**Parameters**

- `$app` [`App`](../App.md)
- `$request` `\Tenma\Http\Request`

**Returns** `\Tenma\Http\Response|\Tenma\Http\WebSocketResponse|\Tenma\Http\StreamedResponse`

### installStorage()

```php
public static function installStorage(): void
```

Request id and CSRF token follow a request into the tasks it starts. Once per process, under Tenma only.

### toWaypoint()

```php
public static function toWaypoint(\Tenma\Http\Request $request): \Waypoint\Http\Request
```

**Parameters**

- `$request` `\Tenma\Http\Request`

**Returns** [`Request`](../Http/Request.md)

### toTenma()

```php
public static function toTenma(\Waypoint\Http\Response $response): \Tenma\Http\Response|\Tenma\Http\WebSocketResponse|\Tenma\Http\StreamedResponse
```

A response handed over with Response::upgrade() goes to Tenma with the request's context; anything else is Tenma's plain Response.

**Parameters**

- `$response` [`Response`](../Http/Response.md)

**Returns** `\Tenma\Http\Response|\Tenma\Http\WebSocketResponse|\Tenma\Http\StreamedResponse`
