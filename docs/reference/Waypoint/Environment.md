---
title: "Environment"
description: "#[Inject]-able facade over EnvironmentOptions (resolved live from the container, so a `new Environment()` always sees the configured one)."
order: 100
generated: true
---

`Waypoint\Environment` · class · namespace [`Waypoint`](index.md)

```php
class Environment
```

#[Inject]-able facade over EnvironmentOptions (resolved live from the container, so a `new Environment()` always sees the configured one).

## Methods

### load()

```php
public function load(?string $dir = null): void
```

**Parameters**

- `$dir` `string|null`: Directory holding .env/config files; defaults to getcwd().

### set()

```php
public function set(string $key, string $value): void
```

**Parameters**

- `$key` `string`
- `$value` `string`

### get()

```php
public function get(string $key, mixed $default = null): mixed
```

**Parameters**

- `$key` `string`
- `$default` `mixed`

**Returns** `mixed`

### isDev()

```php
public function isDev(): bool
```

**Returns** `bool`
