---
title: "ForbiddenException"
description: "HTTP 403 \"Forbidden\": $message becomes the RFC 9457 'detail', $code overrides the status."
order: 100
generated: true
---

`Waypoint\Exceptions\ForbiddenException` · class · namespace [`Waypoint\Exceptions`](index.md)

```php
class ForbiddenException extends \Waypoint\Exceptions\HttpException
```

Extends [`HttpException`](HttpException.md)

HTTP 403 "Forbidden": $message becomes the RFC 9457 'detail', $code overrides the status.

## Methods

### __construct()

```php
public function __construct(?string $message = null, int $code = 403, ?\Throwable $previous = null)
```

**Parameters**

- `$message` `?string`
- `$code` `int`
- `$previous` `?`[`Throwable`](https://www.php.net/manual/en/class.throwable.php)
