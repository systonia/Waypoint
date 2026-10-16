---
title: "SchemaBuilder"
description: "Builds and collects the `components.schemas` of a spec: one object schema per DTO class (public properties, typed; nested DTOs by $ref, recursion- safe), plus the shared RFC 9457 ProblemDetails schema."
order: 100
generated: true
---

`Waypoint\OpenAPI\SchemaBuilder` · final class · namespace [`Waypoint\OpenAPI`](index.md)

```php
final class SchemaBuilder
```

Builds and collects the `components.schemas` of a spec: one object schema
per DTO class (public properties, typed; nested DTOs by $ref, recursion-
safe), plus the shared RFC 9457 ProblemDetails schema. Also owns the
PHP-type -> OpenAPI-type mapping parameters use.

## Methods

### __construct()

```php
public function __construct(private \Waypoint\Logger $logger)
```

**Parameters**

- `$logger` [`Logger`](../Logger.md)

### schemas()

```php
public function schemas(): array
```

**Returns** `array<string, mixed>`

### refFor()

```php
public function refFor(string $fqcn): string
```

Registers $fqcn's schema (once) and returns its "#/components/schemas/..." reference.

**Parameters**

- `$fqcn` `class-string`

**Returns** `string`

### nameFor()

```php
public function nameFor(string $fqcn): string
```

#[Schema(name: ...)] or the short class name.

**Parameters**

- `$fqcn` `class-string`

**Returns** `string`

### ensureProblemDetails()

```php
public function ensureProblemDetails(): void
```

Registers the shared RFC 9457 schema every #[Throws] response $refs.

### generateModelSchema()

```php
public function generateModelSchema(string $fqcn): array
```

An object schema from $fqcn's public properties. A non-nullable property is required:
PHP defaults aren't a usable "optional" signal, since FromArray DTOs give every
property some type-safe default.

**Parameters**

- `$fqcn` `string`

**Returns** `array<string, mixed>`

### parameterSchema()

```php
public static function parameterSchema(?string $type): \stdClass|array
```

Schema for a path/query parameter: unknown/untyped is a string, a class or interface adds no constraint.

**Parameters**

- `$type` `?string`

**Returns** `array<string, mixed>|`[`stdClass`](https://www.php.net/manual/en/class.stdclass.php)

### isScalar()

```php
public static function isScalar(?string $type): bool
```

**Parameters**

- `$type` `?string`

**Returns** `bool`

### mapType()

```php
public static function mapType(string $phpType): string
```

**Parameters**

- `$phpType` `string`

**Returns** `string`
