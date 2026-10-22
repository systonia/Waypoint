---
title: "Router"
description: "Matches a request against the compiled route plans (RouteCompiler, cached by FileSystem) and runs the matched controller method: access checks, per-route middleware, argument binding, and rendering of the result."
order: 100
generated: true
---

`Waypoint\Router` · class · namespace [`Waypoint`](index.md)

```php
class Router
```

Matches a request against the compiled route plans (RouteCompiler, cached
by FileSystem) and runs the matched controller method: access checks,
per-route middleware, argument binding, and rendering of the result.
Also serves compiled view assets and runs CLI tasks.

Plans are plain string-keyed arrays: they are loaded from a `require`d
cache file, so consumers read keys defensively (`?? default`) rather than
trusting a shape, which also keeps a routes.php written by an older
Waypoint working until it is rebuilt.

## Properties

### $staticRoutes

```php
public array $staticRoutes = [];
```

**Type** `array<string, array<string, array<string, mixed>>>`

httpMethod => path => plan

### $dynamicRoutes

```php
public array $dynamicRoutes = [];
```

**Type** `array<string, list<array<string, mixed>>>`

httpMethod => plans, matched by 'regex' in order

### $tasks

```php
public array $tasks = [];
```

**Type** `array<string, mixed>`

fullName => plan (mixed: tests deliberately plant malformed entries to prove executeTask() tolerates them)

## Methods

### __construct()

```php
public function __construct(
    array $controllers,
    array $serviceClasses = [],
    ?\Waypoint\Container $container = null,
    ?array $preloadedCache = null,
    ?\Waypoint\Options\FileSystemOptions $fileSystemOptions = null,
    ?\Waypoint\Plugin\PluginRegistry $plugins = null,
)
```

**Parameters**

- `$controllers` `class-string[]`
- `$serviceClasses` `class-string[]`: Persisted into the cache so trust mode can skip discovery.
- `$container` `?`[`Container`](Container.md)
- `$preloadedCache` `array<string, mixed>|null`: Already-read routes.php data (App::attach() in trust mode), so it isn't `require`d twice.
- `$fileSystemOptions` [`FileSystemOptions`](Options/FileSystemOptions.md)`|null`: Falls back to the container's, then to defaults (caching off).
- `$plugins` [`PluginRegistry`](Plugin/PluginRegistry.md)`|null`: The app's registered plugins (App::plugin()); none when built directly.

### compiledAttributes()

```php
public function compiledAttributes(): array
```

Every attribute on every attached controller (RouteCompiler::exportAllAttributes()) for OpenAPIGenerator:
from the cache's attributes.php when there is a cache, else exported live.

**Returns** `array<class-string, mixed>`

### addCompiledRoute()

```php
public function addCompiledRoute(array $plan, \Waypoint\Enums\RouteType $type): void
```

**Parameters**

- `$plan` `RoutePlan|TaskPlan`
- `$type` [`RouteType`](Enums/RouteType.md)

### exportPlans()

```php
public function exportPlans(): array
```

**Returns** `array{ staticRoutes: array<string, array<string, array<string, mixed>>>, dynamicRoutes: array<string, list<array<string, mixed>>>, tasks: array<string, mixed>, viewAssetFiles: array<string, string>, viewAssetsByName: array<string, array{css: ?string, js: ?string}>, waypointJsFile: ?string, pluginAssetFiles: list<string> }`

### importPlans()

```php
public function importPlans(array $data): void
```

Loads exportPlans()' shape back from a `require`d cache file, narrowing every level and dropping anything malformed.

**Parameters**

- `$data` `array<string, mixed>`

### dispatch()

```php
public function dispatch(
    string $path,
    string $httpMethod,
    \Waypoint\Http\Request $req,
    \Waypoint\Http\Response $res,
): void
```

**Parameters**

- `$path` `string`: Request::$path: trimmed and decoded already
- `$httpMethod` `string`
- `$req` [`Request`](Http/Request.md)
- `$res` [`Response`](Http/Response.md)

### getViewAssets()

```php
public function getViewAssets(string $name): array
```

Compiled {css, js} filenames for a view or layout name (see ViewAssets::discoverViewNames()), or both null.

**Parameters**

- `$name` `string`

**Returns** `array{css: ?string, js: ?string}`

### pluginHooks()

```php
public function pluginHooks(string $type): array
```

The registered plugins' hooks of one type (OpenAPIGenerator asks for OpenApiExtension).

**Parameters**

- `$type` `class-string<T>`

**Returns** `array<string, T>`

**Template parameters**

- `T` of `object`

### getRoutes()

```php
public function getRoutes(): array
```

A flat summary of every route. 'version'/'unversionedPath' fall back to null/path for a
plan predating #[Version], which is what an unversioned route looks like anyway.

**Returns** `array<int, RouteSummary>`

### findRoute()

```php
public function findRoute(array $routes, string $method, string $path): ?object
```

**Parameters**

- `$routes` `array<int, RouteSummary>`
- `$method` `string`
- `$path` `string`

**Returns** `?object`

### executeTask()

```php
public function executeTask(string $name, array $argv = [], int $argc = 0): mixed
```

Runs the task named $name: an exact "prefix:name" key, else the unique task whose name or
":name" suffix matches. The manager gets #[Inject] wiring (minus Request/Response) and is
called as method($argv, $argc).

**Parameters**

- `$name` `string`
- `$argv` `string[]`
- `$argc` `int`

**Returns** `mixed`
