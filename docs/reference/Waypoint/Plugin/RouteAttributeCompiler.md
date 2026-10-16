---
title: "RouteAttributeCompiler"
description: "Turns a plugin's own route attributes into plain data at compile time."
order: 100
generated: true
---

`Waypoint\Plugin\RouteAttributeCompiler` · interface · namespace [`Waypoint\Plugin`](index.md)

```php
interface RouteAttributeCompiler
```

Turns a plugin's own route attributes into plain data at compile time. The
result is stored in the route plan under the plugin's name and handed back
to that plugin's Guard/ResponseHook per request -- never re-reflected.

## Methods

### compile()

```php
public function compile(\ReflectionClass $controller, \ReflectionMethod $method): array
```

**Parameters**

- `$controller` [`ReflectionClass`](https://www.php.net/manual/en/class.reflectionclass.php)`<object>`
- `$method` [`ReflectionMethod`](https://www.php.net/manual/en/class.reflectionmethod.php)

**Returns** `array<string, mixed>`: Empty when the route carries nothing of interest.
