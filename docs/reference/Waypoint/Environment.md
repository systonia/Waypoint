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

#[Inject]-able facade over EnvironmentOptions (resolved live from the container, so a `new Environment()` always
sees the configured one). string()/int()/float()/bool() read a value as that type: without a default, a missing
key throws, naming it, and so does a value that isn't of the type.

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

### string()

```php
public function string(string $key, ?string $default = null): string
```

**Parameters**

- `$key` `string`
- `$default` `?string`

**Returns** `string`

### int()

```php
public function int(string $key, ?int $default = null): int
```

**Parameters**

- `$key` `string`
- `$default` `?int`

**Returns** `int`

### float()

```php
public function float(string $key, ?float $default = null): float
```

**Parameters**

- `$key` `string`
- `$default` `?float`

**Returns** `float`

### bool()

```php
public function bool(string $key, ?bool $default = null): bool
```

"true"/"1"/"yes"/"on" are true, "false"/"0"/"no"/"off"/"" false (FILTER_VALIDATE_BOOLEAN); anything else throws.

**Parameters**

- `$key` `string`
- `$default` `?bool`

**Returns** `bool`

### isDev()

```php
public function isDev(): bool
```

**Returns** `bool`
