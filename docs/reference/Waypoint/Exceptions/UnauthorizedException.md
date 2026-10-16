---
title: "UnauthorizedException"
description: "HTTP 401 \"Unauthorized\": $message becomes the RFC 9457 'detail', $code overrides the status."
order: 100
generated: true
---

`Waypoint\Exceptions\UnauthorizedException` · class · namespace [`Waypoint\Exceptions`](index.md)

```php
class UnauthorizedException extends \Waypoint\Exceptions\HttpException
```

Extends [`HttpException`](HttpException.md)

HTTP 401 "Unauthorized": $message becomes the RFC 9457 'detail', $code overrides the status.

## Methods

### __construct()

```php
public function __construct(?string $message = null, int $code = 401, ?\Throwable $previous = null)
```

**Parameters**

- `$message` `?string`
- `$code` `int`
- `$previous` `?`[`Throwable`](https://www.php.net/manual/en/class.throwable.php)
