---
title: "HttpException"
description: "Base of every exception that maps to an RFC 9457 Problem Details response (see App's default handlers)."
order: 100
generated: true
---

`Waypoint\Exceptions\HttpException` · class · namespace [`Waypoint\Exceptions`](index.md)

```php
class HttpException extends \Exception
```

Extends [`Exception`](https://www.php.net/manual/en/class.exception.php)

Base of every exception that maps to an RFC 9457 Problem Details response
(see App's default handlers). getMessage()/getCode() keep their usual
meaning (a summary, the HTTP status); $title/$detail/$statusCode are the
same information under their RFC names.

## Methods

### __construct()

```php
public function __construct(
    int $statusCode,
    string $title,
    ?string $detail = null,
    string $type = 'about:blank',
    ?string $instance = null,
    ?\Throwable $previous = null,
)
```

**Parameters**

- `$statusCode` `int`
- `$title` `string`: What kind of problem this is ("Not Found"), stable across occurrences.
- `$detail` `string|null`: The specific reason for this occurrence, if any.
- `$type` `string`: A URI reference identifying the problem type; 'about:blank' when $title says it all.
- `$instance` `string|null`: A URI reference to this occurrence (e.g. the request path).
- `$previous` `?`[`Throwable`](https://www.php.net/manual/en/class.throwable.php)

### getStatusCode()

```php
public function getStatusCode(): int
```

**Returns** `int`

### getType()

```php
public function getType(): string
```

**Returns** `string`

### getTitle()

```php
public function getTitle(): string
```

**Returns** `string`

### getDetail()

```php
public function getDetail(): ?string
```

**Returns** `?string`

### getInstance()

```php
public function getInstance(): ?string
```

**Returns** `?string`

### toProblemDetails()

```php
public function toProblemDetails(): array
```

The RFC 9457 members as a json_encode()-ready array; a subclass adds its own extension members (ValidationException's 'errors').

**Returns** `array<string, mixed>`
