---
title: "LocalStorage"
description: "Where a FiberLocal keeps its value, see FiberLocal::useStorage()."
order: 100
generated: true
---

`Waypoint\Support\LocalStorage` · interface · namespace [`Waypoint\Support`](index.md)

```php
interface LocalStorage
```

Where a FiberLocal keeps its value, see FiberLocal::useStorage(). A runtime with async context (such as
Threading\AsyncLocal) lets the value follow a request into the tasks it starts.

## Methods

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
