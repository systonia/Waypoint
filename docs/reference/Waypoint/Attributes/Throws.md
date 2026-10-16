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
Without a status, an HttpException's own default code is used (404 for NotFoundException), 500 for any other.

## Properties

### $exception

```php
public string $exception;
```

### $status

```php
public ?int $status = null;
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
    public ?int $status = null,
    public ?string $description = null,
)
```

**Parameters**

- `$exception` `string`
- `$status` `?int`
- `$description` `?string`
