---
title: Errors
description: Answer with RFC 9457 problem responses by throwing HttpExceptions, write your own exceptions, and register handlers for any exception class.
order: 45
---

A route answers with an error by throwing. Waypoint catches the exception, discards what the route had written, and builds an error response from it.

## HTTP exceptions

[`HttpException`](../reference/Waypoint/Exceptions/HttpException.md) is the base of every exception that maps to a status code. Waypoint ships four:

| Exception | Status | Thrown by Waypoint when |
|---|---|---|
| [`ValidationException`](../reference/Waypoint/Exceptions/ValidationException.md) | 422 | a DTO or a path or query value doesn't validate |
| [`UnauthorizedException`](../reference/Waypoint/Exceptions/UnauthorizedException.md) | 401 | an `#[Authenticated]` route gets no valid JWT |
| [`ForbiddenException`](../reference/Waypoint/Exceptions/ForbiddenException.md) | 403 | a role, permission or CSRF check fails |
| [`NotFoundException`](../reference/Waypoint/Exceptions/NotFoundException.md) | 404 | never; thrown by the app |

Each is answered as an [RFC 9457](https://www.rfc-editor.org/rfc/rfc9457) problem with `Content-Type: application/problem+json`. The bakery's API looks up an order by its code (from `src/Controllers/ApiController.php`):

```php
#[Get('/orders/{code}')]
#[Authenticated]
#[Throws(NotFoundException::class)]
public function order(string $code): OrderSummary
{
    return OrderSummary::of($this->find($code));
}

private function find(string $code): Order
{
    return $this->orders->byCode($code) ?? throw new NotFoundException("No order $code");
}
```

```json
{"type":"about:blank","title":"Not Found","status":404,"detail":"No order X9Z9"}
```

`#[Throws]` adds the `404` to the route's [OpenAPI](openapi.md) description; it doesn't change what is thrown.

The message passed to the constructor becomes `detail`; without one, `detail` is left out. `ValidationException` adds `errors`, field ⇒ message:

```php
throw new ValidationException(errors: ['status' => 'The order is received; its next step is baking.']);
```

```json
{"type":"about:blank","title":"Validation failed","status":422,"detail":"Validation failed","errors":{"status":"The order is received; its next step is baking."}}
```

## Your own exceptions

Domain errors extend `HttpException` with their status and title. `type` can point to a page that explains the problem, and `instance` to the occurrence:

```php
<?php

namespace Bakery\Exceptions;

use Waypoint\Exceptions\HttpException;

final class SoldOutException extends HttpException
{
    public function __construct(public readonly string $product, public readonly int $left)
    {
        parent::__construct(
            statusCode: 409,
            title: 'Sold out',
            detail: "Only $left left of $product.",
            type: 'https://sonnenkorn.test/problems/sold-out',
        );
    }

    public function toProblemDetails(): array
    {
        return [...parent::toProblemDetails(), 'product' => $this->product, 'left' => $this->left];
    }
}
```

```json
{"type":"https:\/\/sonnenkorn.test\/problems\/sold-out","title":"Sold out","status":409,"detail":"Only 2 left of croissant.","product":"croissant","left":2}
```

[`toProblemDetails()`](../reference/Waypoint/Exceptions/HttpException.md#toproblemdetails) returns the members of the response; a subclass adds its own, as `ValidationException` adds `errors`.

## Unexpected exceptions

Any other `Throwable` answers `500` with a fixed body:

```json
{"type":"about:blank","title":"Internal Server Error","status":500}
```

The message never reaches the client, in no environment: an exception's message can contain a file path, a query or a driver error. The exception goes to the [log](logging.md) with its message, class and place, and with the request id that the response carries in `X-Request-ID`, so a support request can be matched to its log line.

## What an error response keeps

When an exception ends a request, the response is built anew. Discarded are what belonged to the failed action: the body, cookies queued with `withCookie()`, and the headers about the content (`Content-*`, `Cache-Control`, `Expires`, `ETag`, `Last-Modified`, `Location`, `Refresh`). Kept are the headers about the request and the app: `X-Request-ID`, CORS and security headers. A login cookie set before a later step failed is therefore never sent with the error.

Exceptions thrown in an app-level middleware go through the same handlers as those from a route, so a middleware can throw `ForbiddenException` as well.

## Exception handlers

[`useExceptionHandler()`](../reference/Waypoint/App.md#useexceptionhandler) registers a handler for an exception class, its subclasses, or an interface. The handler fills in the `Response`; it doesn't send it. The bakery's `Orders::place()` throws its own `OutOfStock`, a plain `RuntimeException`, when an order wants more than is left; a handler turns it into a `409` wherever it escapes:

```php
use Bakery\Models\OutOfStock;
use Waypoint\Http\{Request, Response};

$app->useExceptionHandler(OutOfStock::class, function (OutOfStock $e, Request $req, Response $res) {
    $res->status(409)
        ->withHeader('Content-Type', 'application/problem+json')
        ->write(json_encode([
            'type' => 'about:blank',
            'title' => 'Sold out',
            'status' => 409,
            'detail' => "Not enough {$e->product} left.",
        ]));
});
```

The most specific handler wins: one for the exact class, then for a parent class, then for an implemented interface, and the `Throwable` handler last. Registering a handler for a class again replaces the earlier one, including the built-in ones for `HttpException`, `UnauthorizedException` and `Throwable`. A handler for `Throwable` replaces the generic `500`; it then decides itself what to log and what to show.

## 401 for pages and for APIs

An API client that gets `401` can react to it; a browser that navigated to a page can't. With [`JWTOptions::$loginRedirectUrl`](../reference/Waypoint/Options/JWTOptions.md) set, an `UnauthorizedException` on a page navigation (the browser sends `Sec-Fetch-Mode: navigate`) answers `302` to that URL instead. A `fetch()` call and any client that doesn't send the header still get the `401` problem. See [Authentication](authentication.md#login-pages).

See also: [`HttpException`](../reference/Waypoint/Exceptions/HttpException.md), [`App::useExceptionHandler()`](../reference/Waypoint/App.md#useexceptionhandler), [Logging](logging.md).
