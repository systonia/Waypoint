---
title: "EnvironmentOptions"
description: "The env key/values Waypoint\\Environment reads, each layer overriding the one before: the committed defaults config.json and config.$APP_ENV.json, then .env and .env.$APP_ENV, then the real process environment (system env, $_ENV, $_SERVER), then $localConfigFile, then set()."
order: 100
generated: true
---

`Waypoint\Options\EnvironmentOptions` · class · namespace [`Waypoint\Options`](index.md)

```php
class EnvironmentOptions
```

The env key/values Waypoint\Environment reads, each layer overriding the one before: the committed defaults
config.json and config.$APP_ENV.json, then .env and .env.$APP_ENV, then the real process environment (system
env, $_ENV, $_SERVER), then $localConfigFile, then set(). get()/isDev() load lazily on first use; configure
explicitly only to point load() at another directory.

## Properties

### $localConfigFile

```php
public ?string $localConfigFile = 'config.local.json';
```

A gitignored, per-developer JSON override read last (so it wins over everything); null disables it.

## Methods

### load()

```php
public function load(?string $dir = null): \Waypoint\Options\EnvironmentOptions
```

**Parameters**

- `$dir` `string|null`: Where .env/config files live; defaults to getcwd() (the project root under a normal invocation, never vendor/).

**Returns** [`EnvironmentOptions`](EnvironmentOptions.md)

### set()

```php
public function set(string $key, string $value): \Waypoint\Options\EnvironmentOptions
```

**Parameters**

- `$key` `string`
- `$value` `string`

**Returns** [`EnvironmentOptions`](EnvironmentOptions.md)

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
