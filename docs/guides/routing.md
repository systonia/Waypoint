---
title: Routing
description: Controllers and route attributes, placeholders, HTTP methods, shared settings through base controllers, versions and routes that write the response themselves.
order: 20
---

A route is a public method of a controller class with one of the route attributes `#[Get]`, `#[Post]`, `#[Put]`, `#[Patch]` or `#[Delete]`. There is no route file: the attribute is the route.

The bakery's JSON API (from `src/Controllers/ApiController.php`, shortened) has a route for each thing the till and the delivery app ask:

```php
<?php

namespace Bakery\Controllers;

use Bakery\Models\Order;
use Bakery\Repositories\{Orders, Products, Users};
use Bakery\Requests\{Credentials, StatusChange};
use Bakery\Responses\{BearerToken, OrderSummary};
use Waypoint\Attributes\{Authenticated, Body, Controller, Get, Inject, Patch, Post, Version};
use Waypoint\Exceptions\{NotFoundException, UnauthorizedException};
use Waypoint\JWT;

#[Controller]
#[Version('v1')]
final class ApiController
{
    #[Inject]
    private Users $users;

    #[Inject]
    private Products $products;

    #[Inject]
    private Orders $orders;

    #[Post('/tokens')]
    public function token(#[Body] Credentials $credentials): BearerToken
    {
        $user = $this->users->verify($credentials->email, $credentials->password)
            ?? throw new UnauthorizedException('Wrong email or password.');
        return new BearerToken(JWT::encode($user));
    }

    #[Get('/products')]
    public function products(): array
    {
        return $this->products->all();
    }

    #[Get('/orders/{code}')]
    #[Authenticated]
    public function order(string $code): OrderSummary
    {
        return OrderSummary::of($this->find($code));
    }

    #[Patch('/orders/{code}')]
    #[Authenticated]
    public function advance(string $code, #[Body] StatusChange $change): OrderSummary
    {
        // … move the order to $change->status
    }

    private function find(string $code): Order
    {
        return $this->orders->byCode($code) ?? throw new NotFoundException("No order $code");
    }
}
```

| Request | Method |
|---|---|
| `POST /v1/tokens` | `token()` with the JSON or form body as `Credentials` |
| `GET /v1/products` | `products()` |
| `GET /v1/orders/K7Q2` | `order('K7Q2')` |
| `PATCH /v1/orders/K7Q2` | `advance('K7Q2', …)` with the body as `StatusChange` |

`#[Put]` and `#[Delete]` work the same way. `find()` is private, so it is no route.

## Controllers

A class needs `#[Controller]` for its routes to count. `#[Controller('/backoffice')]` puts every route of the class under that prefix; `#[Controller]` without a path adds none. The route attribute's path follows the prefix, and an empty path (`#[Get('')]`) is the prefix itself.

Slashes are forgiving: the prefix and the route path are joined with exactly one slash, and a trailing slash is dropped, both in the route and in the request. `/backoffice/`, `/backoffice` and `//backoffice` reach the same route.

Controllers are listed in [`attach()`](../reference/Waypoint/App.md#attach). Each request gets a fresh instance of its controller, so a controller may keep what belongs to the request in its properties; services it injects are shared (see [Dependency injection](dependency-injection.md)).

A method carries one route. To answer two paths, write two small methods that call a shared one.

## Placeholders

`{name}` in a path matches one path segment: any text up to the next `/`. The value arrives URL-decoded, once: `/products/k%C3%A4sekuchen` gives `käsekuchen`, and an encoded slash (`%2F`) stays part of its segment instead of splitting it.

Everything around a placeholder matches literally, so a placeholder can share a segment with fixed text:

```php
#[Controller('/backoffice')]
final class BackofficeController
{
    /** The last 90 days of orders as orders.csv or orders.xlsx. */
    #[Get('/orders.{format}')]
    #[Role('owner')]
    public function export(string $format): Sheet
    {
        if (!in_array($format, ['csv', 'xlsx'], true)) {
            throw new NotFoundException("No export as $format");
        }
        return $this->sheet("orders.$format", $this->orders->recent(90));
    }
}
```

matches `/backoffice/orders.csv` and `/backoffice/orders.xlsx`, but not `/backoffice/orders-csv`. (From `src/Controllers/BackofficeController.php`; `Sheet` comes from the [Export plugin](https://github.com/systonia/Waypoint-Export).)

A parameter named like a placeholder receives its value. A scalar parameter (`string`, `int`, `float`, `bool`) is converted to its type, and a value that doesn't fit answers `422` instead of reaching the method: for `#[Get('/products/{id}')]` and `int $id`, `/products/abc` never calls the method. [Binding and validation](binding.md) has the details, including `#[Param('name')]` for a parameter named differently from its placeholder.

Paths without placeholders are looked up first; paths with placeholders are then tried in the order the controllers were attached and the methods declared. `#[Get('/orders/open')]` therefore wins over `#[Get('/orders/{code}')]` wherever both are declared. Two routes with the same method and path, or the same placeholder pattern (`/products/{id}` and `/products/{slug}`), stop the app at boot with an `InvalidArgumentException` naming both actions.

## Methods, HEAD and 405

- A `HEAD` request runs the `GET` route and sends its headers, including `Content-Length`, without the body.
- A path that exists only under other methods answers `405 Method Not Allowed` with an `Allow` header listing them (`GET, HEAD` where there is a `GET` route).
- A path that matches nothing answers `404` with `{"error":"Not found"}`.

Before routes are matched, a `GET` or `HEAD` request may be answered by a file from the [public directory](views.md#static-files) or a compiled view asset, then by the OpenAPI endpoint and plugin endpoints. A request no route matched goes to plugin fallbacks before the `404` (see [Plugins](plugins.md)).

## Shared settings through base controllers

Routes that belong together (a back office, an API version) often share requirements. Put the class-level attributes on an abstract base controller; every route of every subclass inherits them:

```php
<?php

namespace Bakery\Controllers;

use Waypoint\Attributes\{Authenticated, SkipCsrf, Version};

/** What every route of the staff API needs: a version, a bearer token, no CSRF token. */
#[Version('v1')]
#[Authenticated]
#[SkipCsrf]
abstract class StaffApi
{
}
```

```php
<?php

namespace Bakery\Controllers;

use Bakery\Models\Product;
use Bakery\Repositories\Products;
use Waypoint\Attributes\{Controller, Get, Inject, Role};

#[Controller('/stock')]
final class StockApi extends StaffApi
{
    #[Inject]
    private Products $products;

    #[Get('')]
    public function levels(): array
    {
        return array_map(fn (Product $p) => ['slug' => $p->slug, 'stock' => $p->stock], $this->products->all());
    }

    #[Get('/value')]
    #[Role('owner')]
    public function value(): array
    {
        return ['cents' => array_sum(array_map(fn (Product $p) => $p->priceCents * $p->stock, $this->products->all()))];
    }
}
```

`GET /v1/stock` now requires a valid JWT and skips the CSRF check; `GET /v1/stock/value` requires the role `owner` on top. Inherited are `#[Authenticated]`, `#[Role]`, `#[Permissions]`, `#[Middleware]`, `#[Version]`, `#[Sunset]`, `#[NoGzip]`, `#[SkipCsrf]` and PHP's `#[\Deprecated]`. The nearest declaration wins: a subclass or a method can override the base's `#[Role]` or `#[Version]`. The base's middlewares run outside the subclass's own.

`#[Controller]` itself is never inherited: each concrete controller declares its own prefix. Public route methods defined in the base class become routes of every subclass, under that subclass's prefix.

## Versions

`#[Version('v1')]` on a controller or method puts the version in front of the path: `#[Get('/products')]` becomes `/v1/products`. A method's `#[Version]` overrides its class's. Two controllers can serve the same path in two versions:

```php
#[Controller('/orders')]
#[Version('v1')]
final class OrdersV1 { /* … */ }

#[Controller('/orders')]
#[Version('v2')]
final class OrdersV2 { /* … */ }
```

The [OpenAPI document](openapi.md) has one spec per version as well as a combined one. A route without `#[Version]` is served without a prefix; compiling it logs a warning (`… has no #[Version] attribute`) at `warning` level, which only shows when a logger for that level is configured.

To announce that a route goes away, add PHP's own `#[\Deprecated]` and Waypoint's `#[Sunset]`. The responses then carry `Deprecation: true` and the RFC 8594 `Sunset` header:

```php
#[Get('/bread-of-the-day')]
#[\Deprecated]
#[Sunset('2027-06-30')]
public function breadOfTheDay(): array
{
    return ['moved' => '/v1/products'];
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

#[Delete('/carts/current')]
public function forgetCart(Response $res): void
{
    $res->withoutCookie(CartCookie::NAME)->status(204);
}
```

A method with any other return type has its return value rendered; one that returns `null` answers the JSON `null`.

See also: [`Controller`](../reference/Waypoint/Attributes/Controller.md), [`Get`](../reference/Waypoint/Attributes/Get.md), [`Version`](../reference/Waypoint/Attributes/Version.md), [`Sunset`](../reference/Waypoint/Attributes/Sunset.md), [`Router`](../reference/Waypoint/Router.md).
