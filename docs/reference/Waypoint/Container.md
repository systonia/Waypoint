---
title: "Container"
description: "A class-name-keyed singleton registry; a class with no required constructor arguments is constructed on first get()."
order: 100
generated: true
---

`Waypoint\Container` · class · namespace [`Waypoint`](index.md)

```php
class Container implements \Psr\Container\ContainerInterface
```

Implements `Psr\Container\ContainerInterface`

A class-name-keyed singleton registry; a class with no required constructor arguments is constructed on first get().

## Methods

### set()

```php
public function set(object $service): void
```

Registers $service under its own class name, e.g. a service whose constructor takes arguments.

**Parameters**

- `$service` `object`

### bind()

```php
public function bind(string $id, object $service): void
```

Registers $service under an interface (or parent class) name, so get(Interface::class) resolves it.

**Parameters**

- `$id` `class-string`
- `$service` `object`

### get()

```php
public function get(string $id)
```

**Parameters**

- `$id` `class-string<T>`

**Returns** `T`

**Template parameters**

- `T` of `object`

### has()

```php
public function has(string $class): bool
```

**Parameters**

- `$class` `class-string`

**Returns** `bool`

### isRegistered()

```php
public function isRegistered(string $class): bool
```

True only if $class was already constructed (unlike has(), which is also true for "could be").

**Parameters**

- `$class` `class-string`

**Returns** `bool`

### all()

```php
public function all(): array
```

**Returns** `array<class-string, object>`: Every registered instance, by the name it is registered under.
