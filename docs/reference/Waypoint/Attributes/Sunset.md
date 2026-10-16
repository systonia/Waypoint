---
title: "Sunset"
description: "Schedules a route for removal: $date ('YYYY-MM-DD') becomes the RFC 8594 `Sunset` response header, computed once at compile time."
order: 100
generated: true
---

`Waypoint\Attributes\Sunset` · class · namespace [`Waypoint\Attributes`](index.md)

```php
class Sunset
```

Schedules a route for removal: $date ('YYYY-MM-DD') becomes the RFC 8594
`Sunset` response header, computed once at compile time. On a controller
class (every route) or a method (overrides the class). Pair with PHP's
native #[\Deprecated] to also send the `Deprecation` header.

## Properties

### $date

```php
public string $date;
```

## Methods

### __construct()

```php
public function __construct(public string $date)
```

**Parameters**

- `$date` `string`
