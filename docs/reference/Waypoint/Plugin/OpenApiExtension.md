---
title: "OpenApiExtension"
description: "Documents a plugin's own parameters (bound through ArgumentBinder) and return types (rendered through Renderer) in the OpenAPI spec."
order: 100
generated: true
---

`Waypoint\Plugin\OpenApiExtension` · interface · namespace [`Waypoint\Plugin`](index.md)

```php
interface OpenApiExtension
```

Documents a plugin's own parameters (bound through ArgumentBinder) and
return types (rendered through Renderer) in the OpenAPI spec. Asked per
controller parameter and per method return type; the first plugin that
answers wins, and when none does the generator falls back to its own
scalar/object description.

## Methods

### describeParameter()

```php
public function describeParameter(array $parameter): ?array
```

**Parameters**

- `$parameter` `array{name: string, type: string|null, nullable: bool, hasDefault: bool, attributes: list<array{name: string, args: array<int|string, mixed>}>}`: The cached metadata of one controller parameter: PHP name, named type, and its attributes as {name, args}.

**Returns** `array{parameters?: list<array<string, mixed>>, requestBody?: array<string, mixed>}|null`: OpenAPI parameter objects and/or a requestBody object for it, null when the plugin doesn't bind it.

### describeResult()

```php
public function describeResult(string $returnType): ?array
```

**Parameters**

- `$returnType` `string`: The method's declared return type (a class name or PHP type name).

**Returns** `array<string, array<string, mixed>>|null`: The `content` map of the 200 response (`['text/csv' => ['schema' => [...]]]`), null when the plugin doesn't render it.
