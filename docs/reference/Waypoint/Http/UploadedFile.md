---
title: "UploadedFile"
description: "A file from a multipart/form-data request, the same under FPM, Tenma and any PSR-7 server: `$req->file('avatar')?->moveTo($dir . '/' . bin2hex(random_bytes(8)))`."
order: 100
generated: true
---

`Waypoint\Http\UploadedFile` · final class · namespace [`Waypoint\Http`](index.md)

```php
final class UploadedFile
```

A file from a multipart/form-data request, the same under FPM, Tenma and any PSR-7 server:
`$req->file('avatar')?->moveTo($dir . '/' . bin2hex(random_bytes(8)))`.

Name, type and size come from the client: never use the name unchecked as a path, and check the content
itself (finfo, getimagesizefromstring()) before trusting the type.

## Properties

### $name

```php
public readonly string $name;
```

### $type

```php
public readonly string $type;
```

### $size

```php
public readonly int $size;
```

### $error

```php
public readonly int $error = 0;
```

**Type** `int`

One of PHP's UPLOAD_ERR_* constants.

## Methods

### __construct()

```php
public function __construct(
    public readonly string $name,
    public readonly string $type,
    public readonly int $size,
    private readonly \Closure $contents,
    private readonly \Closure $moveTo,
    public readonly int $error = \Waypoint\Http\UPLOAD_ERR_OK,
)
```

For a runtime that hands files over itself; fromString() covers a file held in memory. Request builds PHP's
own uploads ($_FILES) itself.

**Parameters**

- `$name` `string`
- `$type` `string`
- `$size` `int`
- `$contents` [`Closure`](https://www.php.net/manual/en/class.closure.php)`(): string`
- `$moveTo` [`Closure`](https://www.php.net/manual/en/class.closure.php)`(string): void`
- `$error` `int`: One of PHP's UPLOAD_ERR_* constants.

### fromString()

```php
public static function fromString(string $name, string $type, string $contents): \Waypoint\Http\UploadedFile
```

A file held in memory, e.g. from a test or a WebSocket message.

**Parameters**

- `$name` `string`
- `$type` `string`
- `$contents` `string`

**Returns** [`UploadedFile`](UploadedFile.md)

### isValid()

```php
public function isValid(): bool
```

False when the upload failed (too big, partial, ...); see $error.

**Returns** `bool`

### contents()

```php
public function contents(): string
```

**Returns** `string`

### moveTo()

```php
public function moveTo(string $path): void
```

Writes the file to $path; only once.

**Parameters**

- `$path` `string`
