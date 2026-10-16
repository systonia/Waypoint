---
title: "AsyncLocalStorage"
description: "A FiberLocal's value in Tenma's async context: a task a request starts gets a copy of it."
order: 100
generated: true
---

`Waypoint\Tenma\AsyncLocalStorage` · final class · namespace [`Waypoint\Tenma`](index.md)

```php
final class AsyncLocalStorage implements \Waypoint\Support\LocalStorage
```

Implements [`LocalStorage`](../Support/LocalStorage.md)

A FiberLocal's value in Tenma's async context: a task a request starts gets a copy of it.

## Methods

### __construct()

```php
public function __construct()
```

### get()

```php
public function get(): mixed
```

**Returns** `mixed`

### set()

```php
public function set(mixed $value): void
```

null forgets the value

**Parameters**

- `$value` `mixed`
