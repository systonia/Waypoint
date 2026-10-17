---
title: Runtimes
description: Run the app under PHP-FPM and php -S, as a PSR-15 request handler, or from any runtime through App::handle().
order: 155
---

The same front controller runs under every runtime; `serve()` picks the right behaviour. This guide covers the runtimes besides [Tenma](tenma.md).

## PHP-FPM and php -S

Under PHP-FPM, `php -S` and other classic SAPIs, PHP runs the front controller for every request. `serve()` reads the request from the superglobals (`$_SERVER`, `$_GET`, `$_POST`, `$_COOKIE`, `$_FILES`), handles it and sends the response with `header()` and `echo`. Nothing outlives the request, so nothing leaks between requests either.

```bash
php -S localhost:8080 public/index.php
```

Passing `public/index.php` as router script sends every request through the app; Waypoint serves the [public directory](views.md#static-files) itself. Under PHP-FPM the web server serves static files and passes everything else to `public/index.php` (see [Deployment](deployment.md#php-fpm)).

Because every request builds the app again, the [route cache](caching.md) and OPcache matter most here. `APP_ENV` defaults to `production` under FPM and to `development` under `php -S`.

A request that the server hands over in pieces:

- `Authorization` is read from `HTTP_AUTHORIZATION`, or from `AUTHORIZATION` when the server passes it under that name.
- A JSON body is read from `php://input`; form fields and uploads come from `$_POST` and `$_FILES`.
- `HTTPS` set to anything but empty or `off` makes `$req->https` true.

## PSR-15

[`Waypoint\Psr\Handler`](../reference/Waypoint/Psr/Handler.md) makes the app a PSR-15 `RequestHandlerInterface`, for any server or stack that speaks PSR-7: behind PSR-15 middlewares of other packages, or on a PSR-7 server. It takes any PSR-17 factory, for example `nyholm/psr7`. With the `ProductController` from [A first app](../index.md#a-first-app):

```php
<?php

require __DIR__ . '/../vendor/autoload.php';

use Bakery\Controllers\ProductController;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use Waypoint\Psr\Handler;
use Waypoint\Waypoint;

$app = Waypoint::create();
$app->attach([ProductController::class]);

$factory = new Psr17Factory();
$handler = new Handler($app, $factory);

$response = $handler->handle(new ServerRequest('GET', 'http://localhost/products/croissant?quantity=6'));
echo $response->getStatusCode(), ' ', $response->getBody(), "\n";   // 200 {"product":"croissant","quantity":6,"totalCents":1080}
```

Every request is translated from PSR-7 to Waypoint's `Request` and back, with headers, cookies (each `Set-Cookie` separately), the parsed body, uploaded files and the `REMOTE_ADDR` server parameter. PSR-15 middlewares run around the whole app: they see neither route guards nor `#[Authenticated]`, and the app's own middlewares still run inside.

Under Tenma, the handler works through `tenma/psr`:

```php
Tenma::serve(8080, \Tenma\Psr\Psr::handler($handler, $factory));
```

This is the way to combine Waypoint with PSR-15 middlewares under Tenma; without them, `$app->serve()` is the direct and faster way. `Response::upgrade()` (streams, WebSockets) works through the handler only under Tenma with `tenma/psr`.

## Any other runtime

A runtime that receives requests itself builds a [`Request`](../reference/Waypoint/Http/Request.md) with [`Request::fromParts()`](../reference/Waypoint/Http/Request.md#fromparts) and passes it to [`$app->handle()`](../reference/Waypoint/App.md#handle). It returns the finished [`Response`](../reference/Waypoint/Http/Response.md) (compressed, `HEAD` and `Content-Length` applied) without sending anything:

```php
use Waypoint\Http\Request;

$response = $app->handle(Request::fromParts(
    method: 'POST',
    uri: '/v1/tokens',
    headers: ['Content-Type' => 'application/json'],
    body: '{"email": "ben@sonnenkorn.test", "password": "pretzel"}',
    cookies: [],
    https: true,
    ip: '203.0.113.7',
));

$status = $response->getStatus();
$headers = $response->getHeaders();     // every header except Set-Cookie
$cookies = $response->getCookies();     // one Set-Cookie value each
$body = $response->getBody();
```

For `multipart/form-data`, pass the parsed fields as `form:` and the files as `files:` ([`UploadedFile`](../reference/Waypoint/Http/UploadedFile.md) objects, nested like the field names). The Tenma bridge, the PSR-15 handler and the [testing kit](testing.md) are built this way.

See also: [`App`](../reference/Waypoint/App.md), [`Handler`](../reference/Waypoint/Psr/Handler.md), [Tenma](tenma.md), [Deployment](deployment.md).
