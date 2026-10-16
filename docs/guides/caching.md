---
title: Caching
description: Compile routes, services and attributes to a cache directory, choose between validate and trust mode, and enable OPcache.
order: 110
---

`attach()` reads the attributes of every controller with reflection and compiles them into plain route plans: which method answers which path, how each parameter is bound, which middlewares and checks apply. Without a cache directory this happens in memory at every start, which under PHP-FPM means on every request. With one, the plans are written to disk once and read back:

```php
use Waypoint\Options\FileSystemOptions;

$app->configure(function (FileSystemOptions $fs) {
    $fs->cacheDirectory = dirname(__DIR__) . '/var/cache';
});
```

A relative path is resolved against the directory of the front controller. The directory is created when needed and must be writable by the PHP process. There is deliberately no fallback such as the system's temporary directory: the cache is loaded with `require`, and a directory other users can write to would let them plant code.

## What the cache holds

| File | Content |
|---|---|
| `routes.php` | route and task plans, the list of services, data compiled by plugins |
| `attributes.php` | every attribute of every controller, for the [OpenAPI](openapi.md) generator |
| `meta.php` | modification times of the sources the cache was built from |
| `assets/` | compiled view CSS and JS, `waypoint.js` and plugin assets, named by content hash |

## Validate mode

`cacheValidate` is `true` by default. Before using the cache, Waypoint compares the modification times in `meta.php` with the sources, and rebuilds the cache when anything changed:

- a controller's file, its base classes and the traits they use,
- the `#[Middleware]` classes and `#[Body]` DTO classes the routes use,
- view `.css` and `.js` files,
- the registered plugins and their hook classes, their client assets and their `cacheInputs()`,
- Waypoint itself and `waypoint.js`,
- the set of attached controllers and plugins.

This is the right mode during development and a safe one in production: edits are picked up at once. It costs one reflection pass over the controllers and a stat call per source file at every start.

## Trust mode

```php
$app->configure(function (FileSystemOptions $fs) {
    $fs->cacheDirectory = dirname(__DIR__) . '/var/cache';
    $fs->cacheValidate = false;
});
```

With `cacheValidate = false`, an existing cache is used as it is: no reflection, no modification times, one `require` of `routes.php`. This is the fastest way to start under PHP-FPM. In return, nothing notices a change: a deployment that changes code must empty the cache directory, or the old routes stay in effect until it is emptied.

```bash
rm -rf var/cache/*        # in the deployment, after the new code is in place
```

The first request after that builds the cache again.

## Under Tenma

A Tenma worker runs `attach()` once when it starts and keeps the compiled routes in memory for every request. The cache then only shortens the start, and validate mode costs nothing per request. Restarting the server picks up changed code (`tenma --watch` restarts on every change during development).

## OPcache

Enable OPcache wherever response times matter. It keeps compiled PHP code in memory, including the cache files, which are plain PHP arrays. Under PHP-FPM it matters most: without it every request compiles the framework, the app and the cache again.

```ini
opcache.enable=1
opcache.enable_cli=1      ; for php -S and benchmarks on the command line
```

Trust mode and OPcache complement each other: OPcache skips parsing PHP files, trust mode skips Waypoint's discovery on top of that.

See also: [`FileSystemOptions`](../reference/Waypoint/Options/FileSystemOptions.md), [`FileSystem`](../reference/Waypoint/FileSystem.md), [Deployment](deployment.md).
