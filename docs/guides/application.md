---
title: Application setup
description: Create the app, configure it with Options classes, read settings from the environment and .env files, attach controllers and serve.
order: 10
---

Every Waypoint app has one front controller, usually `public/index.php`. It does four things, always in this order:

```php
<?php

require __DIR__ . '/../vendor/autoload.php';

use App\Controllers\{CustomersController, OrdersController};
use Waypoint\Environment;
use Waypoint\Options\{CsrfOptions, EnvironmentOptions, FileSystemOptions, JWTOptions};
use Waypoint\Waypoint;

// 1. create
$app = Waypoint::create();

// 2. configure
$app->configure(fn (EnvironmentOptions $env) => $env->load(dirname(__DIR__)));

$app->configure(function (FileSystemOptions $fs, JWTOptions $jwt, CsrfOptions $csrf, Environment $env) {
    $fs->publicDirectory = __DIR__;
    $fs->cacheDirectory = dirname(__DIR__) . '/var/cache';
    $jwt->secret = $env->get('JWT_SECRET');
    $csrf->secret = $env->get('CSRF_SECRET');
});

// 3. attach
$app->useJwt();
$app->attach([CustomersController::class, OrdersController::class]);

// 4. serve
$app->serve();
```

## Creating the app

[`Waypoint::create()`](../reference/Waypoint/Waypoint.md#create) returns the process's [`App`](../reference/Waypoint/App.md). It creates it on the first call and returns the same instance afterwards, so code that runs later (a CLI task, a test) reaches the same app. [`Waypoint::reset()`](../reference/Waypoint/Waypoint.md#reset) forgets it; the [testing kit](testing.md) does that before every test.

## Configuring with Options classes

Settings live in plain Options classes under `Waypoint\Options`, one per feature. Each property has a working default, and an Options class nobody configured still resolves with those defaults. [`configure()`](../reference/Waypoint/App.md#configure) calls a closure with the app's instance of each class its parameters are typed with:

```php
$app->configure(fn (CompressionOptions $gzip) => $gzip->minBytes = 2048);

$app->configure(function (CorsOptions $cors, SecurityHeaderOptions $headers) {
    $cors->allowOrigin = 'https://shop.example.com';
    $headers->frameOptions = 'SAMEORIGIN';
});
```

A parameter without a class type is an error. `configure()` can be called as often as needed; the instances are shared, so a later call sees what an earlier one set. Besides Options classes a closure may ask for any class the container can build, such as [`Environment`](../reference/Waypoint/Environment.md).

Configure before `attach()`: `FileSystemOptions` and `RendererOptions` are read while the routes are compiled. The others are read per request.

| Options class | Configures | Guide |
|---|---|---|
| [`EnvironmentOptions`](../reference/Waypoint/Options/EnvironmentOptions.md) | where `.env` and config files are read from | [below](#environment-and-env-files) |
| [`FileSystemOptions`](../reference/Waypoint/Options/FileSystemOptions.md) | public directory, route cache, asset URL prefix | [Views](views.md), [Caching](caching.md) |
| [`RendererOptions`](../reference/Waypoint/Options/RendererOptions.md) | views directory and layout | [Views](views.md) |
| [`CompressionOptions`](../reference/Waypoint/Options/CompressionOptions.md) | gzip | [Requests and responses](requests-responses.md#compression) |
| [`CorsOptions`](../reference/Waypoint/Options/CorsOptions.md) | `Access-Control-*` headers | [Middleware](middleware.md#cors) |
| [`SecurityHeaderOptions`](../reference/Waypoint/Options/SecurityHeaderOptions.md) | security response headers | [Middleware](middleware.md#security-headers) |
| [`JWTOptions`](../reference/Waypoint/Options/JWTOptions.md) | JWT sign-in | [Authentication](authentication.md) |
| [`CsrfOptions`](../reference/Waypoint/Options/CsrfOptions.md) | CSRF protection | [Authentication](authentication.md#csrf-protection) |
| [`PermissionOptions`](../reference/Waypoint/Options/PermissionOptions.md) | the permission lookup for `#[Permissions]` | [Authentication](authentication.md#roles-and-permissions) |
| [`ProxyOptions`](../reference/Waypoint/Options/ProxyOptions.md) | trusted reverse proxies | [Proxies](proxies.md) |
| [`LoggerOptions`](../reference/Waypoint/Options/LoggerOptions.md) | PSR-3 loggers | [Logging](logging.md) |
| [`OpenAPIOptions`](../reference/Waypoint/Options/OpenAPIOptions.md) | the OpenAPI document | [OpenAPI](openapi.md) |

A plugin brings its own Options class, configured the same way (see [Plugins](plugins.md)).

## Environment and .env files

Secrets and per-machine settings come from [`Environment`](../reference/Waypoint/Environment.md), never from `getenv()` or `$_ENV` directly. It merges several sources; each later one adds to and overrides the earlier ones:

1. `config.json`, then `config.<APP_ENV>.json`: the committed defaults,
2. `.env`, then `.env.<APP_ENV>`,
3. the real process environment (`getenv()`, then `$_ENV`, then `$_SERVER`), so a variable a server or container sets wins over the files,
4. `config.local.json` (`EnvironmentOptions::$localConfigFile`).

A value set with `Environment::set()` wins over all of them. Missing files are skipped. Keys starting with `HTTP_` are never read from any source: under FPM and CGI those are the request's headers, which the client chose.

The files are read from the current working directory unless `load()` names another one. A front controller in `public/` points it at the project root, so it doesn't matter where the server was started:

```php
$app->configure(fn (EnvironmentOptions $env) => $env->load(dirname(__DIR__)));
```

Without that call the files are loaded from the working directory on the first `get()`.

`.env` holds `KEY=value` lines. Blank lines and lines starting with `#` are skipped, a leading `export ` is dropped, a quoted value is what stands between its quotes, an unquoted one ends at ` #` (a trailing comment), and every value is a string:

```bash
# .env, not committed
APP_ENV=development
JWT_SECRET=6f1d0c0b2a5b4e3f8a9c7d6e5f4a3b2c1d0e9f8a7b6c5d4e3f2a1b0c9d8e7f6a
CSRF_SECRET=0a9b8c7d6e5f4a3b2c1d0e9f8a7b6c5d4e3f2a1b0c9d8e7f6a5b4c3d2e1f0a9b
DATABASE_URL=postgres://lento@localhost/shop
```

The JSON files are flat objects whose values keep their JSON type (`true`, `25`, a list):

```json
{
    "PAGE_SIZE": 25,
    "FEATURE_EXPORT": true
}
```

The intended split: `config.json` and `config.<APP_ENV>.json` hold safe defaults and are committed; `.env` and `config.local.json` hold secrets and personal overrides and are listed in `.gitignore`.

Read values where they are needed: in `configure()`, or in a service that injects `Environment`:

```php
<?php

namespace App\Services;

use Waypoint\Attributes\Inject;
use Waypoint\Environment;

final class Pagination
{
    #[Inject]
    private Environment $env;

    public function pageSize(): int
    {
        return $this->env->int('PAGE_SIZE', 20);
    }
}
```

`get()` returns the value as it was read: a string from `.env` and the process environment, the JSON type from the JSON files. The typed getters convert it:

| Method | Returns |
|---|---|
| `string($key, $default)` | the value as a string |
| `int($key, $default)` | `"25"` or `25` as `25`; `"25px"` throws |
| `float($key, $default)` | `"0.5"` as `0.5` |
| `bool($key, $default)` | `true` for `"true"`, `"1"`, `"yes"`, `"on"`; `false` for `"false"`, `"0"`, `"no"`, `"off"` and `""`; anything else throws |

Without a default, a missing key throws a `RuntimeException` naming it (`Environment value 'DATABASE_URL' is not set.`), so a required setting fails at boot instead of somewhere later.

### APP_ENV

`APP_ENV` names the environment and picks the `.env.<APP_ENV>` and `config.<APP_ENV>.json` files. `Environment::isDev()` is true when it is `development`. Without an `APP_ENV` anywhere, Waypoint decides by how PHP runs: `development` on the command line (`php -S`, CLI tasks) and under `tenma --watch`, `production` under every other server, Tenma without `--watch` included. The request never decides it. A local FPM setup therefore sets `APP_ENV=development` itself, in `.env` or the pool configuration.

`APP_ENV` changes no behaviour of the framework on its own. In particular, an unexpected exception's message never reaches the client, not even in development; it goes to the [log](logging.md).

## Attaching controllers

[`attach()`](../reference/Waypoint/App.md#attach) takes every controller and [CLI task manager](cli-tasks.md) of the app in one list:

```php
$app->attach([
    CustomersController::class,
    OrdersController::class,
    MaintenanceTasks::class,
]);
```

It boots the registered [plugins](plugins.md), finds every service the classes inject (and the services those inject), creates them and compiles the routes. Call it once, after `configure()`, `plugin()`, `use()`, `useCors()` and `useJwt()`.

## Serving

[`serve()`](../reference/Waypoint/App.md#serve) is the last line for every runtime:

| Runtime | What `serve()` does |
|---|---|
| Tenma | starts the HTTP server on `serve($port)`, else `PORT` from the environment, else 8080; Tenma serves `FileSystemOptions::$publicDirectory` itself |
| PHP-FPM, `php -S` | handles the one request in the superglobals and sends the response |
| CLI (`php public/index.php users:sync`) | runs the named [task](cli-tasks.md) and exits with its code |

The lower-level entry points exist for runtimes that need them: [`handle()`](../reference/Waypoint/App.md#handle) takes a [`Request`](../reference/Waypoint/Http/Request.md) and returns the finished [`Response`](../reference/Waypoint/Http/Response.md) without sending it, which is what the Tenma bridge, the PSR-15 handler and the testing kit use. See [Runtimes](runtimes.md).

## Reaching services from the front controller

[`$app->get()`](../reference/Waypoint/App.md#get) returns the container's instance of a class and throws a `ServiceNotFoundException` when it can't be built; [`$app->find()`](../reference/Waypoint/App.md#find) returns `null` instead. They are for code outside controllers and services, such as a script that needs a configured service:

```php
$port = $app->get(Environment::class)->int('PORT', 8080);
$mailer = $app->find(Mailer::class);   // null when the app has none
```

Inside controllers and services, use `#[Inject]` instead (see [Dependency injection](dependency-injection.md)).

See also: [`App`](../reference/Waypoint/App.md), [`Waypoint`](../reference/Waypoint/Waypoint.md), [`Environment`](../reference/Waypoint/Environment.md), [`EnvironmentOptions`](../reference/Waypoint/Options/EnvironmentOptions.md).
