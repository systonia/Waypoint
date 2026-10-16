---
title: "ArgumentResolver"
description: "Turns a compiled argPlan (see RouteCompiler::buildArgPlan()) into the actual argument list for a route method call."
order: 100
generated: true
---

`Waypoint\Routing\ArgumentResolver` · final class · namespace [`Waypoint\Routing`](index.md)

```php
final class ArgumentResolver
```

Turns a compiled argPlan (see RouteCompiler::buildArgPlan()) into the actual argument list for a route method call.

## Methods

### __construct()

```php
public function __construct(private array $binders = [])
```

**Parameters**

- `$binders` `array<string, `[`ArgumentBinder`](../Plugin/ArgumentBinder.md)`>`: plugin name => binder, for 'plugin' entries

### resolve()

```php
public function resolve(
    array $argPlan,
    \Waypoint\Http\Request $req,
    \Waypoint\Http\Response $res,
    array $params,
): array
```

**Parameters**

- `$argPlan` `list<array<string, mixed>>`
- `$req` [`Request`](../Http/Request.md)
- `$res` [`Response`](../Http/Response.md)
- `$params` `array<string, string>`: Route placeholder values.

**Returns** `list<mixed>`
