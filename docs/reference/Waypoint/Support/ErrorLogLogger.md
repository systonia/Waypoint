---
title: "ErrorLogLogger"
description: "Where Waypoint's errors go while the app configured no logger: PHP's error_log, so stderr under Tenma and php -S, the server's error log under FPM."
order: 100
generated: true
---

`Waypoint\Support\ErrorLogLogger` · final class · namespace [`Waypoint\Support`](index.md)

```php
final class ErrorLogLogger extends \Psr\Log\AbstractLogger
```

Extends `Psr\Log\AbstractLogger`

Where Waypoint's errors go while the app configured no logger: PHP's error_log, so stderr under Tenma and
php -S, the server's error log under FPM. One line per entry, the exception's class, message and place with it.

## Methods

### log()

```php
public function log($level, \Stringable|string $message, array $context = []): void
```

**Parameters**

- `$level`
- `$message` [`Stringable`](https://www.php.net/manual/en/class.stringable.php)`|string`
- `$context` `mixed[]`
