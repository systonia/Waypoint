---
title: "Bridge"
description: "Translates between Tenma (PHP with an event loop) and Waypoint for App::__invoke(), which makes an app Tenma's request handler: `new \\Http\\Server(3000, $app)->start()`."
order: 100
generated: true
---

`Waypoint\Tenma\Bridge` · final class · namespace [`Waypoint\Tenma`](index.md)

```php
final class Bridge
```

Translates between Tenma (PHP with an event loop) and Waypoint for App::__invoke(), which makes
an app Tenma's request handler: `new \Http\Server(3000, $app)->start()`. One process then serves many requests,
each in a Fiber of its own, interleaved while they wait on I/O. Only used under Tenma; Waypoint has no
dependency on it.

## Methods

### respond()

```php
public static function respond(\Waypoint\App $app, \Http\Request $request): \Http\Response|\Http\WebSocketResponse|\Http\StreamedResponse
```

One request: Tenma's Request in, through App::handle(), Tenma's Response out.

**Parameters**

- `$app` [`App`](../App.md)
- `$request` `\Http\Request`

**Returns** `\Http\Response|\Http\WebSocketResponse|\Http\StreamedResponse`

### installStorage()

```php
public static function installStorage(): void
```

Request id and CSRF token follow a request into the tasks it starts. Once per process, under Tenma only.

### toWaypoint()

```php
public static function toWaypoint(\Http\Request $request): \Waypoint\Http\Request
```

**Parameters**

- `$request` `\Http\Request`

**Returns** [`Request`](../Http/Request.md)

### toTenma()

```php
public static function toTenma(\Waypoint\Http\Response $response): \Http\Response|\Http\WebSocketResponse|\Http\StreamedResponse
```

A response handed over with Response::upgrade() goes to Tenma with the request's context; anything else is Tenma's plain Response.

**Parameters**

- `$response` [`Response`](../Http/Response.md)

**Returns** `\Http\Response|\Http\WebSocketResponse|\Http\StreamedResponse`
