---
title: "RouteCompiler"
description: "Reflects #[Controller]/#[Manager] classes once into the plain-array route/ task plans Router dispatches against (and FileSystem caches)."
order: 100
generated: true
---

`Waypoint\RouteCompiler` · final class · namespace [`Waypoint`](index.md)

```php
final class RouteCompiler
```

Reflects #[Controller]/#[Manager] classes once into the plain-array route/
task plans Router dispatches against (and FileSystem caches). Everything a
request needs -- argument binding, injections, middleware, auth/CSRF flags,
headers -- is resolved here so dispatch never reflects.

## Methods

### __construct()

```php
public function __construct(private array $attributeCompilers = [], private array $binders = [])
```

**Parameters**

- `$attributeCompilers` `array<string, `[`RouteAttributeCompiler`](Plugin/RouteAttributeCompiler.md)`>`: plugin name => compiler; each result lands in plan['plugins'][name]
- `$binders` `array<string, `[`ArgumentBinder`](Plugin/ArgumentBinder.md)`>`: plugin name => binder, asked after the core attributes and before the implicit scalar binding

### compile()

```php
public function compile(array $classes): array
```

**Parameters**

- `$classes` `class-string[]`

**Returns** `array{staticRoutes: array<string, array<string, RoutePlan>>, dynamicRoutes: array<string, array<int, RoutePlan>>, tasks: array<string, TaskPlan>}`

### exportAllAttributes()

```php
public static function exportAllAttributes(array $controllers): array
```

Every attribute on every controller, property, method and parameter, as
plain {name, args} entries plus each parameter's type -- what
OpenAPIGenerator reads instead of reflecting at request time.

**Parameters**

- `$controllers` `class-string[]`

**Returns** `array<class-string, mixed>`
