---
title: "Throws"
description: "Documents an error response (OpenAPI only): which exception, its HTTP status, and an optional description."
order: 100
generated: true
---

`Waypoint\Attributes\Throws` · class · namespace [`Waypoint\Attributes`](index.md)

```php
class Throws
```

Documents an error response (OpenAPI only): which exception, its HTTP status, and an optional description.

## Properties

### $exception

```php
public string $exception;
```

### $status

```php
public int $status;
```

### $description

```php
public ?string $description = null;
```

## Methods

### __construct()

```php
public function __construct(
    public string $exception,
    public int $status,
    public ?string $description = null,
)
```

**Parameters**

- `$exception` `string`
- `$status` `int`
- `$description` `?string`
