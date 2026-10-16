---
title: "Waypoint"
description: "Process-wide access to the single App: create()/getInstance()/reset(), plus getConfig() for any Options class."
order: 100
generated: true
---

`Waypoint\Waypoint` · class · namespace [`Waypoint`](index.md)

```php
class Waypoint
```

Process-wide access to the single App: create()/getInstance()/reset(), plus getConfig() for any Options class.

## Methods

### create()

```php
public static function create(): \Waypoint\App
```

The process's App, created on the first call; every later call returns the same one.

**Returns** [`App`](App.md)

### getInstance()

```php
public static function getInstance(): ?\Waypoint\App
```

The App create() made, or null before the first create().

**Returns** `?`[`App`](App.md)

### reset()

```php
public static function reset(): void
```

Forgets the App, so the next create() starts a fresh one (the testing kit does this per test).

### getRouter()

```php
public static function getRouter(): \Waypoint\Router
```

**Returns** [`Router`](Router.md)

### getContainer()

```php
public static function getContainer(): \Waypoint\Container
```

**Returns** [`Container`](Container.md)

### getConfig()

```php
public static function getConfig(string $className): object
```

**Parameters**

- `$className` `class-string<T>`

**Returns** `T`

**Template parameters**

- `T` of `object`

### redact()

```php
public static function redact(object $target): array
```

A safe-to-log copy of $target's public, initialized properties: any marked #[Sensitive] or
#[PII] comes back as that attribute's placeholder instead of its value.

**Parameters**

- `$target` `object`

**Returns** `array<string, mixed>`
