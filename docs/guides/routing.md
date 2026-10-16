---
title: Routing
description: Controllers and route attributes, placeholders, HTTP methods, shared settings through base controllers, versions and routes that write the response themselves.
order: 20
---

A route is a public method of a controller class with one of the route attributes `#[Get]`, `#[Post]`, `#[Put]`, `#[Patch]` or `#[Delete]`. There is no route file: the attribute is the route.

```php
<?php

namespace App\Controllers;

use App\Services\ProductCatalog;
use Waypoint\Attributes\{Body, Controller, Delete, Get, Inject, Patch, Post};
use Waypoint\Exceptions\NotFoundException;
use Waypoint\Http\Response;

#[Controller('/products')]
final class ProductsController
{
    #[Inject]
    private ProductCatalog $catalog;

    #[Get('')]
    public function list(int $page = 1): array
    {
        return $this->catalog->page($page);
    }

    #[Get('/{id}')]
    public function show(int $id): array
    {
        return $this->catalog->find($id) ?? throw new NotFoundException("No product $id.");
    }

    #[Post('')]
    public function create(#[Body] NewProduct $product, Response $res): array
    {
        $res->status(201);
        return $this->catalog->add($product);
    }

    #[Patch('/{id}')]
    public function rename(int $id, #[Body] ProductName $name): array
    {
        return $this->catalog->rename($id, $name->name);
    }

    #[Delete('/{id}')]
    public function remove(int $id, Response $res): void
    {
        $this->catalog->remove($id);
        $res->status(204);
    }
}
```

| Request | Method |
|---|---|
| `GET /products?page=2` | `list(2)` |
| `GET /products/7` | `show(7)` |
| `POST /products` | `create()` with the JSON or form body as a `NewProduct` |
| `PATCH /products/7` | `rename(7, …)` |
| `DELETE /products/7` | `remove(7)`, answers `204` |

## Controllers

A class needs `#[Controller]` for its routes to count. `#[Controller('/products')]` puts every route of the class under that prefix; `#[Controller]` without a path adds none. The route attribute's path follows the prefix, and an empty path (`#[Get('')]`) is the prefix itself.

Slashes are forgiving: the prefix and the route path are joined with exactly one slash, and a trailing slash is dropped, both in the route and in the request. `/products/`, `/products` and `//products` reach the same route.

Controllers are listed in [`attach()`](../reference/Waypoint/App.md#attach). Each request gets a fresh instance of its controller, so a controller may keep what belongs to the request in its properties; services it injects are shared (see [Dependency injection](dependency-injection.md)).

A method carries one route. To answer two paths, write two small methods that call a shared one.

## Placeholders

`{name}` in a path matches one path segment: any text up to the next `/`. The value arrives URL-decoded, once: `/products/caf%C3%A9` gives `café`, and an encoded slash (`%2F`) stays part of its segment instead of splitting it.

Everything around a placeholder matches literally, so a placeholder can share a segment with fixed text:

```php
#[Get('/reports/{year}-{month}.csv')]
public function report(int $year, int $month): array
{
    return ['year' => $year, 'month' => $month];
}
```

matches `/reports/2026-10.csv`, but not `/reports/2026-10xcsv`.

A parameter named like a placeholder receives its value. A scalar parameter (`string`, `int`, `float`, `bool`) is converted to its type, and a value that doesn't fit answers `422` instead of reaching the method: `/products/abc` for `int $id` never calls `show()`. [Binding and validation](binding.md) has the details, including `#[Param('name')]` for a parameter named differently from its placeholder.

Paths without placeholders are looked up first; paths with placeholders are then tried in the order the controllers were attached and the methods declared. `#[Get('/products/new')]` therefore wins over `#[Get('/products/{id}')]` wherever both are declared. Two routes with the same method and path, or the same placeholder pattern (`/{id}` and `/{slug}`), stop the app at boot with an `InvalidArgumentException` naming both actions.

## Methods, HEAD and 405

- A `HEAD` request runs the `GET` route and sends its headers, including `Content-Length`, without the body.
- A path that exists only under other methods answers `405 Method Not Allowed` with an `Allow` header listing them (`GET, HEAD` where there is a `GET` route).
- A path that matches nothing answers `404` with `{"error":"Not found"}`.

Before routes are matched, a `GET` or `HEAD` request may be answered by a file from the [public directory](views.md#static-files) or a compiled view asset, then by the OpenAPI endpoint and plugin endpoints. A request no route matched goes to plugin fallbacks before the `404` (see [Plugins](plugins.md)).

## Shared settings through base controllers

Routes that belong together (an admin area, an API version) often share requirements. Put the class-level attributes on an abstract base controller; every route of every subclass inherits them:

```php
<?php

namespace App\Controllers\Admin;

use App\Middleware\AuditTrail;
use Waypoint\Attributes\{Middleware, Role, Version};

#[Version('v1')]
#[Role('admin')]
#[Middleware(AuditTrail::class)]
abstract class AdminController
{
}
```

```php
<?php

namespace App\Controllers\Admin;

use Waypoint\Attributes\{Controller, Get};

#[Controller('/admin/users')]
final class UsersController extends AdminController
{
    #[Get('')]
    public function list(): array
    {
        return [['id' => 1, 'name' => 'Ada']];
    }
}
```

`GET /v1/admin/users` now requires a JWT with the role `admin` and runs through the `AuditTrail` middleware. Inherited are `#[Authenticated]`, `#[Role]`, `#[Permissions]`, `#[Middleware]`, `#[Version]`, `#[Sunset]`, `#[NoGzip]`, `#[SkipCsrf]` and PHP's `#[\Deprecated]`. The nearest declaration wins: a subclass or a method can override the base's `#[Role]` or `#[Version]`. The base's middlewares run outside the subclass's own.

`#[Controller]` itself is never inherited: each concrete controller declares its own prefix. Public route methods defined in the base class become routes of every subclass, under that subclass's prefix.

## Versions

`#[Version('v1')]` on a controller or method puts the version in front of the path: `#[Get('/users')]` becomes `/v1/users`. A method's `#[Version]` overrides its class's. Two controllers can serve the same path in two versions:

```php
#[Controller('/orders')]
#[Version('v1')]
final class OrdersV1Controller { /* … */ }

#[Controller('/orders')]
#[Version('v2')]
final class OrdersV2Controller { /* … */ }
```

The [OpenAPI document](openapi.md) has one spec per version as well as a combined one. A route without `#[Version]` is served without a prefix; compiling it logs a warning (`… has no #[Version] attribute`) at `warning` level, which only shows when a logger for that level is configured.

To announce that a route goes away, add PHP's own `#[\Deprecated]` and Waypoint's `#[Sunset]`. The responses then carry `Deprecation: true` and the RFC 8594 `Sunset` header:

```php
#[Get('/legacy-report')]
#[\Deprecated]
#[Sunset('2027-06-30')]
public function legacyReport(): array
{
    return ['moved' => '/v2/reports'];
}
```

The date is `YYYY-MM-DD`; a malformed date is logged and no `Sunset` header is sent. `#[\Deprecated]` is PHP's own attribute, so PHP also raises a deprecation notice each time the method runs, as for any deprecated method.

## What a route returns

| Return value | Response |
|---|---|
| array, object, scalar, `null` | JSON, `Content-Type: application/json` |
| [`View`](../reference/Waypoint/Http/View.md) | HTML rendered from a template, see [Views](views.md) |
| [`Redirect`](../reference/Waypoint/Http/Redirect.md) | a `3xx` with `Location` |
| with `#[FileFormatter]`: `SplFileInfo` or string | a file or raw body |
| with `#[SimpleXmlFormatter]` | XML |
| a type a plugin renders | whatever the plugin writes |

[Requests and responses](requests-responses.md) covers each of them.

### Routes that write the response themselves

A method declared `: void` (or `: never`) answers with nothing but what it wrote to the `Response`. Nothing is rendered after it returns, so an empty body stays empty:

```php
#[Get('/health')]
public function health(Response $res): void
{
    $res->withHeader('Content-Type', 'text/plain')->write("ok\n");
}

#[Delete('/sessions/current')]
public function signOut(Response $res): void
{
    $res->withoutCookie('auth_token')->status(204);
}
```

A method with any other return type has its return value rendered; one that returns `null` answers the JSON `null`.

See also: [`Controller`](../reference/Waypoint/Attributes/Controller.md), [`Get`](../reference/Waypoint/Attributes/Get.md), [`Version`](../reference/Waypoint/Attributes/Version.md), [`Sunset`](../reference/Waypoint/Attributes/Sunset.md), [`Router`](../reference/Waypoint/Router.md).
