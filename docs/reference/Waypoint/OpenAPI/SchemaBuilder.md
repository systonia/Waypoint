---
title: "SchemaBuilder"
description: "Builds and collects the `components.schemas` of a spec: one object schema per DTO class (public properties, typed; nested DTOs by $ref, recursion- safe; enums inline with their values; lists from #[ListOf]), plus the shared RFC 9457 ProblemDetails schema."
order: 100
generated: true
---

`Waypoint\OpenAPI\SchemaBuilder` · final class · namespace [`Waypoint\OpenAPI`](index.md)

```php
final class SchemaBuilder
```

Builds and collects the `components.schemas` of a spec: one object schema
per DTO class (public properties, typed; nested DTOs by $ref, recursion-
safe; enums inline with their values; lists from #[ListOf]), plus the shared RFC 9457 ProblemDetails schema. Also owns the
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

#[Schema(name: ...)] or the short class name; when another class has that name already, the full class name
with dots for backslashes (`App.Billing.Item`), so two DTOs never share a schema.

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

An object schema from $fqcn's public properties without #[Ignore]. A property is required when it has no default and is not
nullable: FromArray reports exactly those as missing. A nullable one allows null (`type: [x, "null"]`), and one
the request cannot set (readonly, protected(set), private(set)) is `readOnly`, as FromArray never fills it.

**Parameters**

- `$fqcn` `string`

**Returns** `array<string, mixed>`

### nullable()

```php
public static function nullable(array $schema): array
```

$schema that also allows null: `type: [x, "null"]` (with null among an enum's values), or anyOf for a $ref.

**Parameters**

- `$schema` `array<string, mixed>`

**Returns** `array<string, mixed>`

### arraySchema()

```php
public function arraySchema(?string $type, bool $map = false): array
```

An array of $type elements (#[ListOf], #[Returns]): a list as items, a map as additionalProperties.

**Parameters**

- `$type` `?string`
- `$map` `bool`

**Returns** `array<string, mixed>`

### typeSchema()

```php
public function typeSchema(string $type): array
```

A scalar keyword, an enum (inline, with its values) or a class (by $ref).

**Parameters**

- `$type` `string`

**Returns** `array<string, mixed>`

### enumSchema()

```php
public static function enumSchema(string $enum): array
```

`{type, enum}`: a backed enum's values, a pure enum's case names.

**Parameters**

- `$enum` `string`

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
