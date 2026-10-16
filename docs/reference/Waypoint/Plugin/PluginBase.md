---
title: "PluginBase"
description: "Plugin with every optional part defaulting to \"nothing\"; override what you need."
order: 100
generated: true
---

`Waypoint\Plugin\PluginBase` · abstract class · namespace [`Waypoint\Plugin`](index.md)

```php
abstract class PluginBase implements \Waypoint\Plugin\Plugin
```

Implements [`Plugin`](Plugin.md)

Plugin with every optional part defaulting to "nothing"; override what you need.

## Methods

### requires()

```php
public function requires(): array
```

**Returns** `list<class-string<`[`Plugin`](Plugin.md)`>>`: Plugins that must be registered before this one.

### classes()

```php
public function classes(): array
```

**Returns** `list<class-string>`: Controllers and #[Manager] classes attach() adds.

### middlewares()

```php
public function middlewares(): array
```

**Returns** `list<callable>`: App-level middlewares, added at attach() in registration order.

### hooks()

```php
public function hooks(): array
```

**Returns** `list<object>`: Instances of Endpoint, Fallback, RouteAttributeCompiler, Guard, ResponseHook, ArgumentBinder, Renderer, OpenApiExtension, ViewHelper, ClientAsset.

### cacheInputs()

```php
public function cacheInputs(): array
```

**Returns** `array<string, int>`: {path => mtime} of files whose change must rebuild the route cache.
