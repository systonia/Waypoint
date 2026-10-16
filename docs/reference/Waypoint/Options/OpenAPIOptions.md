---
title: "OpenAPIOptions"
description: "Turns the OpenAPI endpoint on and configures the document-level parts of the spec: info, servers, tags, security."
order: 100
generated: true
---

`Waypoint\Options\OpenAPIOptions` · class · namespace [`Waypoint\Options`](index.md)

```php
class OpenAPIOptions
```

Uses [`FromArray`](../FromArray.md)

Turns the OpenAPI endpoint on and configures the document-level parts of the spec: info, servers, tags, security.

## Properties

### $enabled

```php
public bool $enabled = false;
```

Serve the spec and Swagger UI at all (see Waypoint\OpenAPI\OpenAPIEndpoint). Off by default.

### $path

```php
public string $path = '/openapi' { get; set; }
```

URL prefix the endpoint lives under, e.g. '/openapi' -> /openapi/spec.json; normalized to a leading and no trailing slash.

### $title

```php
public string $title = 'API Documentation';
```

### $version

```php
public string $version = '1.0.0';
```

### $description

```php
public string $description = 'Generated API documentation';
```

### $servers

```php
public array $servers = [];
```

**Type** `array<int, array<string, mixed>>`

### $tags

```php
public array $tags = [];
```

**Type** `array<string, string>`

### $securitySchemes

```php
public array $securitySchemes = [];
```

**Type** `array<string, mixed>`

### $security

```php
public array $security = [];
```

**Type** `array<int, array<string, array<int, string>>>`

### $externalDocs

```php
public ?array $externalDocs = null;
```

**Type** `array{description?: string, url?: string}|null`

### $errors

```php
public array $errors { get; }
```

**Type** `array<string, string>`

field => message; attribute rules first, then fail() entries, required fields left unset, and nested DTOs' errors ("address.city").

### $isValid

```php
public bool $isValid { get; }
```

## Methods

### toArray()

```php
public function toArray(): array
```

**Returns** `array<string, mixed>`: The spec's top-level keys, empty ones omitted.

### __construct()

```php
public function __construct(array $data = [])
```

**Parameters**

- `$data` `array<string, mixed>`

### fail()

```php
public function fail(string $field, string $message): static
```

Records a failure the attributes can't know about; a later one for the same field replaces the earlier.

**Parameters**

- `$field` `string`
- `$message` `string`

**Returns** `static`

### hydrateFromArray()

```php
protected function hydrateFromArray(array $data): void
```

Split out so a using class can declare its own __construct() and still call this.

**Parameters**

- `$data` `array<string, mixed>`

### fromArrayExcludes()

```php
protected function fromArrayExcludes(): array
```

Property names hydration skips (e.g. a nested DTO set some other way). A method, since a trait property's default can't be overridden.

**Returns** `array<int, string>`
