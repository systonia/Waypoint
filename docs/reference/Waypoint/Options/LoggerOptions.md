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
accepts: `$opts->add($logger, [LogLevel::ERROR])`.

## Methods

### add()

```php
public function add(\Psr\Log\LoggerInterface|array $logger, array|string|null $levels = null): \Waypoint\Options\LoggerOptions
```

**Parameters**

- `$logger` `LoggerInterface|array<int, mixed>`: One logger or a list (non-loggers are skipped).
- `$levels` `string|array<mixed>|null`: One level or a list of them; none for every level.

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

**Returns** `array<int, array{logger: LoggerInterface, levels: array<string, bool>}>`: When none were added: PHP's error_log for errors and worse, so an error never vanishes unseen; `add(new NullLogger())` silences it.
