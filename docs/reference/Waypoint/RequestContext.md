---
title: "RequestContext"
description: "The current request's correlation id (Request::$id), held as a container singleton for the duration of one request so Logger can stamp it onto every line without call sites passing it around."
order: 100
generated: true
---

`Waypoint\RequestContext` · class · namespace [`Waypoint`](index.md)

```php
class RequestContext
```

The current request's correlation id (Request::$id), held as a container
singleton for the duration of one request so Logger can stamp it onto
every line without call sites passing it around. Set/cleared by
App::handle(); per Fiber, so requests running interleaved in Fibers keep their own.

## Methods

### __construct()

```php
public function __construct()
```

### setRequestId()

```php
public function setRequestId(?string $id): void
```

**Parameters**

- `$id` `?string`

### getRequestId()

```php
public function getRequestId(): ?string
```

Null outside of a request (CLI task, or before handle() ran).

**Returns** `?string`
