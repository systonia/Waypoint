---
title: "PluginRegistry"
description: "The registered plugins, in registration order, and their hooks sorted by type."
order: 100
generated: true
---

`Waypoint\Plugin\PluginRegistry` · final class · namespace [`Waypoint\Plugin`](index.md)

```php
final class PluginRegistry
```

The registered plugins, in registration order, and their hooks sorted by type.

## Methods

### add()

```php
public function add(\Waypoint\Plugin\Plugin $plugin): void
```

**Parameters**

- `$plugin` [`Plugin`](Plugin.md)

### all()

```php
public function all(): array
```

**Returns** `list<`[`Plugin`](Plugin.md)`>`

### boot()

```php
public function boot(\Waypoint\Container $container): void
```

Calls each plugin's boot(), if it has one, with what its parameters' types name (see Plugin).

**Parameters**

- `$container` [`Container`](../Container.md)

### classes()

```php
public function classes(): array
```

**Returns** `list<class-string>`

### middlewares()

```php
public function middlewares(): array
```

**Returns** `list<callable>`

### cacheInputs()

```php
public function cacheInputs(): array
```

**Returns** `array<string, int>`

### hooks()

```php
public function hooks(string $type): array
```

Every hook of one type, with the owning plugin's name.

**Parameters**

- `$type` `class-string<T>`

**Returns** `array<string, T>`: plugin name => hook (one per plugin; a plugin provides each hook type at most once)

**Template parameters**

- `T` of `object`

### viewHelpers()

```php
public function viewHelpers(): array
```

**Returns** `array<string, callable>`: All view helpers; a name offered twice is a boot error.
