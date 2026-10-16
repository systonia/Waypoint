---
title: "ArgumentBinder"
description: "Binds a plugin's own parameter attribute (or type) to a controller method argument."
order: 100
generated: true
---

`Waypoint\Plugin\ArgumentBinder` · interface · namespace [`Waypoint\Plugin`](index.md)

```php
interface ArgumentBinder
```

Binds a plugin's own parameter attribute (or type) to a controller method argument.

## Methods

### plan()

```php
public function plan(\ReflectionParameter $parameter): ?array
```

**Parameters**

- `$parameter` [`ReflectionParameter`](https://www.php.net/manual/en/class.reflectionparameter.php)

**Returns** `array<string, mixed>|null`: Compile-time data for this parameter, or null if not this binder's business.

### resolve()

```php
public function resolve(
    array $plan,
    \Waypoint\Http\Request $req,
    \Waypoint\Http\Response $res,
    array $routeParams,
): mixed
```

**Parameters**

- `$plan` `array<string, mixed>`: What plan() returned.
- `$req` [`Request`](../Http/Request.md)
- `$res` [`Response`](../Http/Response.md)
- `$routeParams` `array<string, string>`: Route placeholder values.

**Returns** `mixed`
