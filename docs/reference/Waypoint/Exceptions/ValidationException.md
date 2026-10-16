---
title: "ValidationException"
description: "HTTP 422: field-level validation errors, exposed as the RFC 9457 extension member 'errors'."
order: 100
generated: true
---

`Waypoint\Exceptions\ValidationException` · class · namespace [`Waypoint\Exceptions`](index.md)

```php
class ValidationException extends \Waypoint\Exceptions\HttpException
```

Extends [`HttpException`](HttpException.md)

HTTP 422: field-level validation errors, exposed as the RFC 9457 extension member 'errors'.

## Properties

### $errors

```php
protected array $errors = [];
```

**Type** `array<array-key, mixed>`

field => message; for a #[Body(of: ...)] collection, index => {field => message}.

## Methods

### __construct()

```php
public function __construct(
    $message = 'Validation failed',
    array $errors = [],
    int $code = 422,
    ?\Throwable $previous = null,
)
```

**Parameters**

- `$message` `string|`[`Message`](../Enums/Message.md)
- `$errors` `array<array-key, mixed>`
- `$code` `int`
- `$previous` `?`[`Throwable`](https://www.php.net/manual/en/class.throwable.php)

### getErrors()

```php
public function getErrors(): array
```

**Returns** `array<array-key, mixed>`

### toProblemDetails()

```php
public function toProblemDetails(): array
```

**Returns** `array<array-key, mixed>`
