---
title: "Plugin"
description: "A plugin is one Composer package with one class implementing this and at most one Options class."
order: 100
generated: true
---

`Waypoint\Plugin\Plugin` · interface · namespace [`Waypoint\Plugin`](index.md)

```php
interface Plugin
```

A plugin is one Composer package with one class implementing this and at
most one Options class. It only declares: which classes to attach, which
middlewares to add, which hooks (the other interfaces in this namespace)
it provides, and which files its compiled output depends on. Registered
with `$app->plugin(new XPlugin())` before attach(). See PluginBase for
the no-op defaults.

## Methods

### name()

```php
public function name(): string
```

Short unique name ('i18n'): the plan namespace for its route data, and the prefix in logs.

**Returns** `string`

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
