---
title: "Property"
description: "Documents a DTO property in generated OpenAPI schemas."
order: 100
generated: true
---

`Waypoint\Attributes\Property` · class · namespace [`Waypoint\Attributes`](index.md)

```php
class Property
```

Documents a DTO property in generated OpenAPI schemas. Purely descriptive
metadata read by Waypoint\OpenAPI\OpenAPIGenerator; it has no effect on
request binding or validation.

## Properties

### $description

```php
public ?string $description = null;
```

### $format

```php
public ?string $format = null;
```

### $example

```php
public mixed $example = null;
```

### $deprecated

```php
public bool $deprecated = false;
```

## Methods

### __construct()

```php
public function __construct(
    public ?string $description = null,
    public ?string $format = null,
    public mixed $example = null,
    public bool $deprecated = false,
)
```

**Parameters**

- `$description` `?string`
- `$format` `?string`
- `$example` `mixed`
- `$deprecated` `bool`
