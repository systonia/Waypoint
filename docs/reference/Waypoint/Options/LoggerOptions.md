---
title: "LoggerOptions"
description: "The PSR-3 loggers Waypoint\\Logger fans out to, each with the levels it accepts: `$opts->add($logger, [LogLevel::ERROR])`."
order: 100
generated: true
---

`Waypoint\Options\LoggerOptions` · class · namespace [`Waypoint\Options`](index.md)

```php
class LoggerOptions
```

The PSR-3 loggers Waypoint\Logger fans out to, each with the levels it
accepts: `$opts->add($logger, [LogLevel::ERROR])`. An instance also serves
as the small {levels, name} descriptor add() accepts as $options.

## Properties

### $levels

```php
public array $levels = [];
```

**Type** `string[]`

Accepted levels when used as a descriptor; empty means all.

### $name

```php
public ?string $name = null;
```

Channel name when used as a descriptor.

## Methods

### __construct()

```php
public function __construct(array $levels = [], ?string $name = null)
```

**Parameters**

- `$levels` `string[]`
- `$name` `?string`

### add()

```php
public function add(\Psr\Log\LoggerInterface|array $logger, $options = null): \Waypoint\Options\LoggerOptions
```

**Parameters**

- `$logger` `LoggerInterface|array<int, mixed>`: One logger or a list (non-loggers are skipped).
- `$options` [`LoggerOptions`](LoggerOptions.md)`|string|array<int|string, mixed>|null`: A descriptor, one level, a list of levels, or {levels, name}.

**Returns** [`LoggerOptions`](LoggerOptions.md)

### addMono()

```php
public function addMono(string $channel, mixed $handler, array $levels = []): \Waypoint\Options\LoggerOptions
```

Adds a Monolog logger for $channel with $handler, if Monolog is installed.

**Parameters**

- `$channel` `string`
- `$handler` `mixed`
- `$levels` `string[]`

**Returns** [`LoggerOptions`](LoggerOptions.md)

### getLoggers()

```php
public function getLoggers(): array
```

**Returns** `array<int, array{logger: LoggerInterface, levels: array<string, bool>, name: string|null}>`: When none were added: PHP's error_log for errors and worse, so an error never vanishes unseen; `add(new NullLogger())` silences it.
