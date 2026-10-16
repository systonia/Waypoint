---
title: "OpenAPIGenerator"
description: "Builds an OpenAPI 3.1 document from the Router's route list and the attribute cache RouteCompiler::exportAllAttributes() wrote, with no controller reflection at request time; only DTO classes are reflected, by SchemaBuilder."
order: 100
generated: true
---

`Waypoint\OpenAPI\OpenAPIGenerator` · class · namespace [`Waypoint\OpenAPI`](index.md)

```php
class OpenAPIGenerator
```

Builds an OpenAPI 3.1 document from the Router's route list and the
attribute cache RouteCompiler::exportAllAttributes() wrote, with no
controller reflection at request time; only DTO classes are reflected,
by SchemaBuilder.

## Methods

### __construct()

```php
public function __construct(private \Waypoint\Router $router)
```

**Parameters**

- `$router` [`Router`](../Router.md)

### generate()

```php
public function generate(?string $version = null): array
```

**Parameters**

- `$version` `string|null`: Null: every unversioned route plus, per (method, path) group, only the newest #[Version]. A version string: only that version's routes (spec.vX.json).

**Returns** `array<string, mixed>`

### hasVersion()

```php
public function hasVersion(string $version): bool
```

True if any compiled route carries #[Version($version)], so a spec.vX.json for an unknown version can 404.

**Parameters**

- `$version` `string`

**Returns** `bool`

### buildPaths()

```php
protected function buildPaths(?string $version = null): array
```

**Parameters**

- `$version` `?string`

**Returns** `array<string, mixed>`

### selectEligibleRoutes()

```php
protected function selectEligibleRoutes(array $routes, ?string $version): array
```

**Parameters**

- `$routes` `array<int, mixed>`
- `$version` `?string`

**Returns** `array<int, mixed>`: Exact-version filter when $version is given; otherwise every unversioned route plus the newest version of each (method, unversionedPath) group.

### compareVersions()

```php
protected function compareVersions(string $a, string $b): int
```

<=> on the numeric runs of two version strings, so v10 > v2.

**Parameters**

- `$a` `string`
- `$b` `string`

**Returns** `int`

### versionSortKey()

```php
protected function versionSortKey(string $version): array
```

**Parameters**

- `$version` `string`

**Returns** `int[]`: 'v1.2' -> [1, 2]; no digits -> [0].

### getHandlerSpec()

```php
protected function getHandlerSpec(mixed $route): ?array
```

[controllerClass, methodName] from a route's handlerSpec: a plain pair, or one nested under
'spec'. The class isn't verified to exist: a missing attribute-cache entry just means "no attributes".

**Parameters**

- `$route` `mixed`

**Returns** `array{0: string, 1: string}|null`

### buildOperation()

```php
protected function buildOperation(
    string $controllerClass,
    string $methodName,
    array $methodAttrs,
    array $classAttrs,
    string $path = '',
): array
```

**Parameters**

- `$controllerClass` `string`
- `$methodName` `string`
- `$methodAttrs` `list<array{name: string, args: array<int|string, mixed>}>`
- `$classAttrs` `list<array{name: string, args: array<int|string, mixed>}>`
- `$path` `string`

**Returns** `array<string, mixed>`

### extractParameters()

```php
protected function extractParameters(string $controllerClass, string $methodName, string $path = ''): array
```

`parameters` and `requestBody` for one operation, from the cached parameter metadata
(attributes, PHP type, nullability, default). An unattributed scalar is Router's implicit
placeholder-then-query binding: a path parameter when $path has a placeholder of its name, otherwise a query parameter.

**Parameters**

- `$controllerClass` `string`
- `$methodName` `string`
- `$path` `string`

**Returns** `array{0: list<array<string, mixed>>, 1: array<string, mixed>|null}`
