---
title: "RouteGuard"
description: "The per-request access checks a compiled route carries: #[Authenticated]/ #[Role]/#[Permissions] (folded into 'authenticated'/'role'/'permissions' by RouteCompiler) and CSRF verification for state-changing methods unless #[SkipCsrf]."
order: 100
generated: true
---

`Waypoint\Routing\RouteGuard` · final class · namespace [`Waypoint\Routing`](index.md)

```php
final class RouteGuard
```

The per-request access checks a compiled route carries: #[Authenticated]/
#[Role]/#[Permissions] (folded into 'authenticated'/'role'/'permissions'
by RouteCompiler) and CSRF verification for state-changing methods
unless #[SkipCsrf]. A plan predating a key defaults to the safe side:
no auth requirement, but CSRF checked.

## Methods

### __construct()

```php
public function __construct(private ?\Waypoint\Container $container)
```

**Parameters**

- `$container` `?`[`Container`](../Container.md)

### check()

```php
public function check(array $route, string $httpMethod, \Waypoint\Http\Request $req): void
```

**Parameters**

- `$route` `array<string, mixed>`
- `$httpMethod` `string`
- `$req` [`Request`](../Http/Request.md)
