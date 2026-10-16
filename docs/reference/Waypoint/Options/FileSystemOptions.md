---
title: "FileSystemOptions"
description: "Where the compiled route/DI cache lives and where public static files are served from."
order: 100
generated: true
---

`Waypoint\Options\FileSystemOptions` · class · namespace [`Waypoint\Options`](index.md)

```php
class FileSystemOptions
```

Where the compiled route/DI cache lives and where public static files are served from. Relative paths
resolve against the front controller's directory. Without a cacheDirectory nothing is written to or
`require`d from disk: routes compile in memory on every boot (set one in production).

## Properties

### $cacheDirectory

```php
public ?string $cacheDirectory = null { get; set; }
```

### $publicDirectory

```php
public ?string $publicDirectory = null { get; set; }
```

### $assetsPath

```php
public string $assetsPath = '/assets' { get; set; }
```

URL prefix compiled view/layout CSS/JS is served under; normalized to a leading and no trailing slash.

### $cacheValidate

```php
public bool $cacheValidate = true;
```

True (default): the cache is re-verified against controller/view mtimes on every request.
False ("trust mode"): the cache is loaded blindly; clearing it on deploy is your job.

## Methods

### buildPath()

```php
public function buildPath(string $directory): string
```

$directory as given if absolute, else under the running script's directory (getcwd() as a last resort); no trailing slash.

**Parameters**

- `$directory` `string`

**Returns** `string`

### isCacheEnabled()

```php
public function isCacheEnabled(): bool
```

**Returns** `bool`

### getCacheDirectory()

```php
public function getCacheDirectory(): string
```

The configured cache directory. There is deliberately no fallback such as the system temp
directory: a cache other users or other apps can write to would be `require`d as code.

**Returns** `string`

### getPublicDirectory()

```php
public function getPublicDirectory(): string
```

**Returns** `string`
