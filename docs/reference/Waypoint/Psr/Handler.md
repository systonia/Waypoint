---
title: "Handler"
description: "The app as a PSR-15 handler, for any server that speaks PSR-7: `new Handler($app, new Psr17Factory())`."
order: 100
generated: true
---

`Waypoint\Psr\Handler` · final class · namespace [`Waypoint\Psr`](index.md)

```php
final class Handler implements \Psr\Http\Server\RequestHandlerInterface
```

Implements `Psr\Http\Server\RequestHandlerInterface`

The app as a PSR-15 handler, for any server that speaks PSR-7: `new Handler($app, new Psr17Factory())`.
Under Tenma through tenma/psr, under any other PSR-7 server, or
behind PSR-15 middlewares of other packages. `new \Http\Server(3000, $app)->start()` stays the direct way under Tenma.

Response::upgrade() needs Tenma and tenma/psr: it goes out as Tenma\Psr\Upgrade.

## Methods

### __construct()

```php
public function __construct(
    private readonly \Waypoint\App $app,
    private readonly \Psr\Http\Message\ResponseFactoryInterface&\Psr\Http\Message\StreamFactoryInterface $factory,
)
```

**Parameters**

- `$app` [`App`](../App.md)
- `$factory` `\Psr\Http\Message\ResponseFactoryInterface&\Psr\Http\Message\StreamFactoryInterface`

### handle()

```php
public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
```

Handles a request and produces a response.

May call other collaborating code to generate the response.

**Parameters**

- `$request` `\Psr\Http\Message\ServerRequestInterface`

**Returns** `\Psr\Http\Message\ResponseInterface`

### toWaypoint()

```php
public static function toWaypoint(\Psr\Http\Message\ServerRequestInterface $request): \Waypoint\Http\Request
```

**Parameters**

- `$request` `\Psr\Http\Message\ServerRequestInterface`

**Returns** [`Request`](../Http/Request.md)
