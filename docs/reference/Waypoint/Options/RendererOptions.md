---
title: "RendererOptions"
description: "Where view templates live and which layout wraps them."
order: 100
generated: true
---

`Waypoint\Options\RendererOptions` · class · namespace [`Waypoint\Options`](index.md)

```php
class RendererOptions
```

Uses [`FromArray`](../FromArray.md)

Where view templates live and which layout wraps them.

## Properties

### $directory

```php
public ?string $directory = null { get; set; }
```

Resolved like FileSystemOptions paths; null falls back to the running script's directory.

### $layout

```php
public string $layout = '_Layout';
```

Layout file name including .php ('_Layout.php'). The default has no extension and so matches no file: no layout until configured.

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

### __construct()

```php
public function __construct(array $options = [])
```

**Parameters**

- `$options` `array<string, mixed>`

### toArray()

```php
public function toArray(): array
```

**Returns** `array<string, string>`

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
