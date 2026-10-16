---
title: "PropertyInjector"
description: "Sets #[Inject] properties."
order: 100
generated: true
---

`Waypoint\Routing\PropertyInjector` · final class · namespace [`Waypoint\Routing`](index.md)

```php
final class PropertyInjector
```

Sets #[Inject] properties. Two sources: a compiled propInject plan
(controllers, #[Middleware] classes, task managers; reflected once by
RouteCompiler), or live reflection for objects user code constructs
itself (a View, an $app->use()d middleware, a discovered service).

## Constants

### PER_REQUEST

```php
public const PER_REQUEST = ['Waypoint\\Http\\Request', 'Waypoint\\Http\\Response', 'Waypoint\\Router'];
```

Supplied per request by Router, never container-managed services.

## Methods

### __construct()

```php
public function __construct(
    private ?\Waypoint\Container $container,
    private ?\Waypoint\Router $router = null,
)
```

**Parameters**

- `$container` `?`[`Container`](../Container.md)
- `$router` `?`[`Router`](../Router.md)

### injectPlanned()

```php
public function injectPlanned(
    object $target,
    mixed $propInject,
    ?\Waypoint\Http\Request $req = null,
    ?\Waypoint\Http\Response $res = null,
): void
```

Wires a compiled {name, type} plan. Without $req/$res (a CLI task) Request/Response entries are skipped.

**Parameters**

- `$target` `object`
- `$propInject` `mixed`
- `$req` `?`[`Request`](../Http/Request.md)
- `$res` `?`[`Response`](../Http/Response.md)

### injectReflected()

```php
public function injectReflected(object $target): void
```

Wires every #[Inject] property found by reflection, skipping per-request types and anything the container can't provide.

**Parameters**

- `$target` `object`

### namedType()

```php
public static function namedType(\ReflectionProperty|\ReflectionParameter $member): ?string
```

The declared single class/scalar type name, or null for untyped/union/intersection (nothing #[Inject] could resolve).

**Parameters**

- `$member` [`ReflectionProperty`](https://www.php.net/manual/en/class.reflectionproperty.php)`|`[`ReflectionParameter`](https://www.php.net/manual/en/class.reflectionparameter.php)

**Returns** `?string`
