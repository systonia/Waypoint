---
title: "Middleware"
description: "Attaches a MiddlewareBase subclass to a controller class (runs for every route on it, before any method-level middleware) or one route method: `#[Middleware(CartCookie::class)]`."
order: 100
generated: true
---

`Waypoint\Attributes\Middleware` · class · namespace [`Waypoint\Attributes`](index.md)

```php
class Middleware
```

Attaches a MiddlewareBase subclass to a controller class (runs for every
route on it, before any method-level middleware) or one route method:
`#[Middleware(CartCookie::class)]`. Its handle() is what runs; a class
that doesn't extend MiddlewareBase fails the route compile.

## Properties

### $class

```php
public string $class;
```

## Methods

### __construct()

```php
public function __construct(public string $class)
```

**Parameters**

- `$class` `string`
