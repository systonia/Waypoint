---
title: "FiberLocal"
description: "One value per Fiber, for state that belongs to a single request."
order: 100
generated: true
---

`Waypoint\Support\FiberLocal` · final class · namespace [`Waypoint\Support`](index.md)

```php
final class FiberLocal implements \Waypoint\Support\LocalStorage
```

Implements [`LocalStorage`](LocalStorage.md)

One value per Fiber, for state that belongs to a single request. A runtime that runs several requests
interleaved in Fibers of one process (each request in its own Fiber) then keeps them apart. Outside a
Fiber (FPM, php -S, CLI) it is a single slot, which a Fiber without its own value also sees.

PHP doesn't know which Fiber started another, so a task a request starts in a Fiber of its own sees
nothing. A runtime that knows (see useStorage()) can hand the value on.

**Template parameters**

- `T`

## Methods

### __construct()

```php
public function __construct()
```

### capture()

```php
public static function capture(): \Closure
```

Takes the current values of every FiberLocal and returns a function that sets them again, in whatever
Fiber or task calls it. For work that runs after the request was reset but belongs to it: App::handle()
captures before it resets an upgraded request, and Tenma\Bridge restores in the WebSocket handler.

**Returns** [`Closure`](https://www.php.net/manual/en/class.closure.php)`(): void`

### useStorage()

```php
public static function useStorage(?\Closure $create): void
```

Keeps the values of every FiberLocal in what $create returns from now on, e.g. a runtime's async context
that follows a request into the tasks it starts. Call it before the first request.

  FiberLocal::useStorage(fn () => new class implements LocalStorage { ... Tenma\AsyncLocal ... });

**Parameters**

- `$create` `(`[`Closure`](https://www.php.net/manual/en/class.closure.php)`(): `[`LocalStorage`](LocalStorage.md)`)|null`

### get()

```php
public function get(): mixed
```

**Returns** `T|null`

### set()

```php
public function set(mixed $value): void
```

**Parameters**

- `$value` `T|null`: null forgets this Fiber's value
