---
title: "NotFoundException"
description: "HTTP 404 \"Not Found\": $message becomes the RFC 9457 'detail', $code overrides the status."
order: 100
generated: true
---

`Waypoint\Exceptions\NotFoundException` · class · namespace [`Waypoint\Exceptions`](index.md)

```php
class NotFoundException extends \Waypoint\Exceptions\HttpException implements \Psr\Container\NotFoundExceptionInterface
```

Extends [`HttpException`](HttpException.md)  
Implements `Psr\Container\NotFoundExceptionInterface`

HTTP 404 "Not Found": $message becomes the RFC 9457 'detail', $code overrides the status. It implements PSR-11's NotFoundExceptionInterface for handlers registered on that interface; Container::get() throws ServiceNotFoundException.

## Methods

### __construct()

```php
public function __construct(?string $message = null, int $code = 404, ?\Throwable $previous = null)
```

**Parameters**

- `$message` `?string`
- `$code` `int`
- `$previous` `?`[`Throwable`](https://www.php.net/manual/en/class.throwable.php)
