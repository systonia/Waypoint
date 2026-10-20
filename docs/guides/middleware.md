---
title: Middleware
description: Run code before and after requests with MiddlewareBase, app-wide or per route, and add security headers and CORS.
order: 60
---

A middleware is a class that extends [`MiddlewareBase`](../reference/Waypoint/Http/MiddlewareBase.md) and overrides `before()`, `after()` or both. The bakery gives every browser a cart id in a cookie, so its cart survives going from page to page (from `src/Services/CartCookie.php`):

```php
<?php

namespace Bakery\Services;

use Waypoint\Http\{MiddlewareBase, Request, Response};
use Waypoint\Support\Uuid;

final class CartCookie extends MiddlewareBase
{
    public const NAME = 'cart';

    public static function valid(string $id): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $id) === 1;
    }

    protected function before(Request $req, Response $res): bool
    {
        if ($req->method === 'GET' && !self::valid($req->cookies[self::NAME] ?? '')) {
            // This request's components see it already, the browser sends it from the next request on
            $req->cookies[self::NAME] = Uuid::v4();
            $res->withCookie(self::NAME, $req->cookies[self::NAME], maxAge: 30 * 86400);
        }
        return true;
    }
}
```

`before()` runs before the request goes on, `after()` once the response is built. Returning `false` from `before()` stops the request there: neither the rest of the chain nor `after()` runs, and the response as `before()` left it is sent. Both are optional; the defaults do nothing. A middleware that wants to stop a request with an error throws an [HTTP exception](errors.md) instead.

A middleware that answers by itself, for the days the shop takes a break:

```php
<?php

namespace Bakery\Services;

use Waypoint\Attributes\Inject;
use Waypoint\Environment;
use Waypoint\Http\{MiddlewareBase, Request, Response};

final class ClosedForHolidays extends MiddlewareBase
{
    #[Inject]
    private Environment $env;

    protected function before(Request $req, Response $res): bool
    {
        if (!$this->env->bool('SHOP_CLOSED', false)) {
            return true;
        }
        $res->status(503)
            ->withHeader('Retry-After', '86400')
            ->withHeader('Content-Type', 'text/plain')
            ->write("Sonnenkorn is on holiday. We bake again from Monday.\n");
        return false;
    }
}
```

A middleware with `after()` measures how long a request took:

```php
<?php

namespace Bakery\Services;

use Waypoint\Http\{MiddlewareBase, Request, Response};

final class ServerTiming extends MiddlewareBase
{
    private float $start = 0.0;

    protected function before(Request $req, Response $res): bool
    {
        $this->start = hrtime(true);
        return true;
    }

    protected function after(Request $req, Response $res): void
    {
        $milliseconds = (hrtime(true) - $this->start) / 1e6;
        $res->withHeader('Server-Timing', sprintf('app;dur=%.1f', $milliseconds));
    }
}
```

## App-wide middleware

[`$app->use()`](../reference/Waypoint/App.md#use) adds a middleware instance that runs for every request, before routing:

```php
$app->use(new SecurityHeadersMiddleware());
$app->use(new CartCookie());
$app->useJwt();

$app->attach([ShopController::class, SessionController::class, BackofficeController::class, ApiController::class]);
```

The first one registered is the outermost: its `before()` runs first and its `after()` last. App-wide middlewares also see requests that end in a `404`, `405`, a file from the public directory under PHP-FPM, or an exception; an exception they throw goes through the [exception handlers](errors.md#exception-handlers). `#[Inject]` properties of an instance passed to `use()` are set when it is registered, and the instance is shared by all requests, so such a middleware keeps nothing per request in its properties. `CartCookie` and `ClosedForHolidays` qualify; `ServerTiming` above, which keeps the start time, belongs on routes (or keeps the time in a [`FiberLocal`](../reference/Waypoint/Support/FiberLocal.md)).

Plugins add their middlewares at `attach()`, after the app's own. `useCors()` and `useJwt()` are middlewares too and take their place in the order of the calls.

## Route middleware

`#[Middleware(ClassName::class)]` on a controller or a route method attaches a middleware to those routes only:

```php
<?php

namespace Bakery\Services;

use Waypoint\Exceptions\ForbiddenException;
use Waypoint\Http\{MiddlewareBase, Request, Response};

/** Stock changes only from the bakery's own network, not from a phone at home. */
final class BakeryNetworkOnly extends MiddlewareBase
{
    protected function before(Request $req, Response $res): bool
    {
        if (!str_starts_with($req->ip, '192.168.10.')) {
            throw new ForbiddenException('The stock can only be changed in the bakery.');
        }
        return true;
    }
}
```

```php
<?php

namespace Bakery\Controllers;

use Bakery\Services\{BakeryNetworkOnly, ServerTiming};
use Waypoint\Attributes\{Authenticated, Controller, Get, Layout, Middleware};
use Waypoint\Http\View;

#[Controller('/backoffice')]
#[Authenticated]
#[Middleware(ServerTiming::class)]
#[Layout('_Backoffice.php')]
final class BackofficeController
{
    #[Get('')]
    public function board(): View
    {
        return new View('Backoffice/Board');
    }

    #[Get('/stock')]
    #[Middleware(BakeryNetworkOnly::class)]
    public function stock(): View
    {
        return new View('Backoffice/Stock');
    }
}
```

- The attribute is repeatable. Middlewares run in the order declared, a base controller's first, then the class's, then the method's.
- A route middleware is created anew for each request, so it may keep per-request values in properties (as `ServerTiming` does), and its `#[Inject]` properties may include `Request` and `Response`.
- It runs after the route's access checks (`#[Authenticated]`, `#[Role]`, `#[Permissions]`, CSRF and plugin guards) and before the arguments are bound.
- A class that doesn't exist or doesn't extend `MiddlewareBase` fails the route compilation with an `InvalidArgumentException`, rather than leaving the route without the check it was declared to have.

A `MiddlewareBase` subclass without per-request properties works both ways: as `#[Middleware(ClosedForHolidays::class)]` on some routes and as `$app->use(new ClosedForHolidays())` on all.

## Security headers

[`SecurityHeadersMiddleware`](../reference/Waypoint/Http/SecurityHeadersMiddleware.md) sets the standard hardening headers on every response:

```php
use Waypoint\Http\SecurityHeadersMiddleware;
use Waypoint\Options\SecurityHeaderOptions;

$app->configure(function (SecurityHeaderOptions $headers) {
    $headers->frameOptions = 'SAMEORIGIN';
    $headers->cspEnabled = true;
    $headers->csp = "default-src 'self'; img-src 'self' data:";
});

$app->use(new SecurityHeadersMiddleware());
```

| Header | Default |
|---|---|
| `X-Content-Type-Options` | `nosniff` |
| `X-Frame-Options` | `DENY` |
| `Referrer-Policy` | `strict-origin-when-cross-origin` |
| `Strict-Transport-Security` | `max-age=31536000; includeSubDomains`, only on requests that arrived over HTTPS |
| `Content-Security-Policy` | off; set `cspEnabled` and `csp` |

Each has an `…Enabled` switch in [`SecurityHeaderOptions`](../reference/Waypoint/Options/SecurityHeaderOptions.md). The headers are set in `before()`, so error responses carry them as well. A route or a later middleware that sets one of these headers replaces it; one that an earlier middleware set is kept. Register it first, so every response passes through it. HTTPS behind a proxy is recognised once the proxy is trusted (see [Proxies](proxies.md)). `waypoint.js` evaluates nothing, so it runs under a strict CSP.

## CORS

For an API called from browsers on other origins (the bakery's till app on its own host, say), configure [`CorsOptions`](../reference/Waypoint/Options/CorsOptions.md) and call [`useCors()`](../reference/Waypoint/App.md#usecors):

```php
use Waypoint\Options\CorsOptions;

$app->configure(function (CorsOptions $cors) {
    $cors->allowOrigin = 'https://till.sonnenkorn.test';
    $cors->allowMethods = 'GET, POST, PATCH, OPTIONS';
    $cors->allowHeaders = 'Content-Type, Authorization';
    $cors->exposeHeaders = 'X-Request-ID';
    $cors->maxAge = 86400;
});

$app->useCors();
```

Every response then carries the configured `Access-Control-*` headers; a property left `null` leaves its header out. Any `OPTIONS` request is answered right there with `204` and the headers, before routing. `allowCredentials = true` sends `Access-Control-Allow-Credentials: true`, which browsers accept only together with a single concrete origin, not `*`.

See also: [`MiddlewareBase`](../reference/Waypoint/Http/MiddlewareBase.md), [`Middleware`](../reference/Waypoint/Attributes/Middleware.md), [`SecurityHeaderOptions`](../reference/Waypoint/Options/SecurityHeaderOptions.md), [`CorsOptions`](../reference/Waypoint/Options/CorsOptions.md).
