---
title: "FileSystem"
description: "The on-disk compilation cache under FileSystemOptions::$cacheDirectory: routes.php (plans + service list), meta.php (source mtimes that produced it), attributes.php (RouteCompiler::exportAllAttributes() for OpenAPI), and assets/ (content-hashed view CSS/JS, kept out of routes.php so the `require`d file stays small)."
order: 100
generated: true
---

`Waypoint\FileSystem` · final class · namespace [`Waypoint`](index.md)

```php
final class FileSystem
```

The on-disk compilation cache under FileSystemOptions::$cacheDirectory:
routes.php (plans + service list), meta.php (source mtimes that produced
it), attributes.php (RouteCompiler::exportAllAttributes() for OpenAPI),
and assets/ (content-hashed view CSS/JS, kept out of routes.php so the
`require`d file stays small).

## Methods

### __construct()

```php
public function __construct(
    private \Waypoint\Options\FileSystemOptions $options = new \Waypoint\Options\FileSystemOptions(…),
)
```

**Parameters**

- `$options` [`FileSystemOptions`](Options/FileSystemOptions.md)

### getRouteFile()

```php
public function getRouteFile(): string
```

**Returns** `string`

### getMetaFile()

```php
public function getMetaFile(): string
```

**Returns** `string`

### getAttributesFile()

```php
public function getAttributesFile(): string
```

**Returns** `string`

### getAssetsDirectory()

```php
public function getAssetsDirectory(): string
```

**Returns** `string`

### hasCachedRoutes()

```php
public function hasCachedRoutes(): bool
```

Trust mode's whole check: one stat call.

**Returns** `bool`

### loadCachedRouteData()

```php
public function loadCachedRouteData(): ?array
```

The decoded routes.php (plans + 'services'), or null without a cache.

**Returns** `array<string, mixed>|null`

### loadCachedServices()

```php
public function loadCachedServices(): ?array
```

The class list compiled into routes.php, or null without a cache.

**Returns** `string[]|null`

### isAvailable()

```php
public function isAvailable(array $controllers, array $extraMeta = []): bool
```

True if the cache exists and was built from exactly the current sources: every controller
file (by mtime) plus $extraMeta (view .css/.js mtimes), no more and no fewer -- a cache built
for a larger controller set must not keep serving a removed controller's routes.

**Parameters**

- `$controllers` `class-string[]`
- `$extraMeta` `array<string, int>`: path => mtime

**Returns** `bool`

### storeFromRouter()

```php
public function storeFromRouter(
    \Waypoint\Router $router,
    array $controllers,
    array $serviceClasses,
    array $extraMeta = [],
): void
```

Writes routes.php, meta.php and attributes.php from the Router's current plans.

**Parameters**

- `$router` [`Router`](Router.md)
- `$controllers` `class-string[]`
- `$serviceClasses` `class-string[]`
- `$extraMeta` `array<string, int>`: The same view-asset mtimes isAvailable() will compare against next time.

### storeAttributes()

```php
public function storeAttributes(array $controllers): void
```

**Parameters**

- `$controllers` `class-string[]`

### loadAttributes()

```php
public function loadAttributes(): array
```

**Returns** `array<class-string, mixed>`: See RouteCompiler::exportAllAttributes() for the shape.

### loadToRouter()

```php
public function loadToRouter(\Waypoint\Router $router): void
```

**Parameters**

- `$router` [`Router`](Router.md)

### storeViewAssetFiles()

```php
public function storeViewAssetFiles(array $files): array
```

Writes each compiled asset to assets/{filename} and returns {filename => mime} for routes.php.
Filenames are content hashes, so an existing file is never rewritten -- and a stale one is
never cleaned up here (clearing the cache directory is the deployment's job).

**Parameters**

- `$files` `array<string, array{content: string, mime: string}>`

**Returns** `array<string, string>`

### readViewAssetFile()

```php
public function readViewAssetFile(string $filename): ?string
```

One stored asset's content, or null if it's gone (e.g. the cache directory was cleared). $filename is a manifest key, never raw request input.

**Parameters**

- `$filename` `string`

**Returns** `?string`
