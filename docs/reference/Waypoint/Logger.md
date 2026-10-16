---
title: "Logger"
description: "PSR-3 facade that fans out to every logger registered via App::configure(function (LoggerOptions $opts) { $opts->add(...); })."
order: 100
generated: true
---

`Waypoint\Logger` · class · namespace [`Waypoint`](index.md)

```php
class Logger implements \Psr\Log\LoggerInterface
```

Implements `Psr\Log\LoggerInterface`  
Uses `Psr\Log\LoggerTrait`

PSR-3 facade that fans out to every logger registered via
App::configure(function (LoggerOptions $opts) { $opts->add(...); }).

Stateless on purpose: each call resolves the live LoggerOptions from the
container, so a `new Logger()` (what Container auto-instantiation and
#[Inject] produce) always sees whatever configure() set up.

## Methods

### log()

```php
public function log($level, \Stringable|string $message, array $context = []): void
```

**Parameters**

- `$level` `mixed`: PSR-3 level string (anything else is ignored).
- `$message` [`Stringable`](https://www.php.net/manual/en/class.stringable.php)`|string`
- `$context` `mixed[]`: 'request_id' is stamped in automatically (see RequestContext) unless already present.

### emergency()

```php
public function emergency(\Stringable|string $message, array $context = []): void
```

System is unusable.

**Parameters**

- `$message` [`Stringable`](https://www.php.net/manual/en/class.stringable.php)`|string`
- `$context` `array`

### alert()

```php
public function alert(\Stringable|string $message, array $context = []): void
```

Action must be taken immediately.

Example: Entire website down, database unavailable, etc. This should
trigger the SMS alerts and wake you up.

**Parameters**

- `$message` [`Stringable`](https://www.php.net/manual/en/class.stringable.php)`|string`
- `$context` `array`

### critical()

```php
public function critical(\Stringable|string $message, array $context = []): void
```

Critical conditions.

Example: Application component unavailable, unexpected exception.

**Parameters**

- `$message` [`Stringable`](https://www.php.net/manual/en/class.stringable.php)`|string`
- `$context` `array`

### error()

```php
public function error(\Stringable|string $message, array $context = []): void
```

Runtime errors that do not require immediate action but should typically
be logged and monitored.

**Parameters**

- `$message` [`Stringable`](https://www.php.net/manual/en/class.stringable.php)`|string`
- `$context` `array`

### warning()

```php
public function warning(\Stringable|string $message, array $context = []): void
```

Exceptional occurrences that are not errors.

Example: Use of deprecated APIs, poor use of an API, undesirable things
that are not necessarily wrong.

**Parameters**

- `$message` [`Stringable`](https://www.php.net/manual/en/class.stringable.php)`|string`
- `$context` `array`

### notice()

```php
public function notice(\Stringable|string $message, array $context = []): void
```

Normal but significant events.

**Parameters**

- `$message` [`Stringable`](https://www.php.net/manual/en/class.stringable.php)`|string`
- `$context` `array`

### info()

```php
public function info(\Stringable|string $message, array $context = []): void
```

Interesting events.

Example: User logs in, SQL logs.

**Parameters**

- `$message` [`Stringable`](https://www.php.net/manual/en/class.stringable.php)`|string`
- `$context` `array`

### debug()

```php
public function debug(\Stringable|string $message, array $context = []): void
```

Detailed debug information.

**Parameters**

- `$message` [`Stringable`](https://www.php.net/manual/en/class.stringable.php)`|string`
- `$context` `array`
