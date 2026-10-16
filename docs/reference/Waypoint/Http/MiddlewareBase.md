---
title: "MiddlewareBase"
description: "Base class for every #[Middleware(...)] handler, also usable directly on `$app->use()` via __invoke()."
order: 100
generated: true
---

`Waypoint\Http\MiddlewareBase` · abstract class · namespace [`Waypoint\Http`](index.md)

```php
abstract class MiddlewareBase
```

Base class for every #[Middleware(...)] handler, also usable directly on
`$app->use()` via __invoke(). handle() always runs before() -> $next() ->
after(); a subclass overrides before()/after(). before() returning false
vetoes the request: $next() and after() are skipped (e.g. after sending
its own 403), and the response built so far is what gets sent.

## Methods

### handle()

```php
final public function handle(
    \Waypoint\Http\Request $req,
    \Waypoint\Http\Response $res,
    callable $next,
): mixed
```

**Parameters**

- `$req` [`Request`](Request.md)
- `$res` [`Response`](Response.md)
- `$next` `callable`

**Returns** `mixed`

### __invoke()

```php
final public function __invoke(
    \Waypoint\Http\Request $req,
    \Waypoint\Http\Response $res,
    callable $next,
): mixed
```

**Parameters**

- `$req` [`Request`](Request.md)
- `$res` [`Response`](Response.md)
- `$next` `callable`

**Returns** `mixed`

### before()

```php
protected function before(\Waypoint\Http\Request $req, \Waypoint\Http\Response $res): bool
```

Return false to stop the chain here.

**Parameters**

- `$req` [`Request`](Request.md)
- `$res` [`Response`](Response.md)

**Returns** `bool`

### after()

```php
protected function after(\Waypoint\Http\Request $req, \Waypoint\Http\Response $res): void
```

**Parameters**

- `$req` [`Request`](Request.md)
- `$res` [`Response`](Response.md)
