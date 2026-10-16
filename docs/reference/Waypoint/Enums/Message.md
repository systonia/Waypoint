---
title: "Message"
order: 100
generated: true
---

`Waypoint\Enums\Message` · enum · namespace [`Waypoint\Enums`](index.md)

```php
enum Message: string
```

## Cases

### Forbidden

```php
case Forbidden = 'Forbidden';
```

### NotFound

```php
case NotFound = 'Not Found';
```

### Unauthorized

```php
case Unauthorized = 'Unauthorized';
```

### ValidationFailed

```php
case ValidationFailed = 'Validation failed';
```

### CsrfTokenInvalid

```php
case CsrfTokenInvalid = 'Invalid or missing CSRF token';
```

### GeneratorPropertyDoesNotExist

```php
case GeneratorPropertyDoesNotExist = 'OpenAPIGenerator: Property type "{property}" does not exist (property "{name}" in class "{rc}")';
```

### GeneratorPropertyHasNoType

```php
case GeneratorPropertyHasNoType = 'OpenAPIGenerator: Property "{name}" in class "{rc}" has no type.';
```

### GeneratorClassDoesNotExist

```php
case GeneratorClassDoesNotExist = 'OpenAPIGenerator: generateModelSchema - class "{fqcn}" does not exist.';
```

### GeneratorPropertyDoesNotExist2

```php
case GeneratorPropertyDoesNotExist2 = 'OpenAPIGenerator: Parameter type "{type}" does not exist in method {method}.';
```

### RouteUnversioned

```php
case RouteUnversioned = 'RouteCompiler: {method} {path} has no #[Version] attribute; served without a version prefix.';
```

### RouteInvalidSunsetDate

```php
case RouteInvalidSunsetDate = 'RouteCompiler: {method} {path} has an invalid #[Sunset] date "{date}" (expected YYYY-MM-DD); no Sunset header will be sent for it.';
```

## Methods

### format()

```php
public function format(array $vars = []): string
```

Replaces each {key} placeholder with the matching $vars value.

**Parameters**

- `$vars` `array<array-key, mixed>`

**Returns** `string`

### interpolate()

```php
public function interpolate(...$vars): string
```

format() with named arguments: Message::X->interpolate(method: $m, path: $p).

**Parameters**

- `...$vars` `mixed`

**Returns** `string`
