[![Latest Version](https://img.shields.io/packagist/v/systonia/waypoint.svg)](https://packagist.org/packages/systonia/waypoint)
![PHP Version](https://img.shields.io/badge/PHP-8.5-blue)
![Build](https://github.com/Systonia/Waypoint/actions/workflows/build.yaml/badge.svg)
![Dependency Audit](https://github.com/systonia/Waypoint/actions/workflows/audit.yaml/badge.svg)
![PHPStan Level 10](https://github.com/systonia/Waypoint/actions/workflows/phpstan.yaml/badge.svg)
[![PSR-3](https://img.shields.io/badge/PSR--3-compatible-brightgreen.svg)](https://www.php-fig.org/psr/psr-3/)
[![PSR-4](https://github.com/Systonia/Waypoint/actions/workflows/psr-4.yaml/badge.svg)](https://www.php-fig.org/psr/psr-4/)
![License](https://img.shields.io/github/license/Systonia/Waypoint)

# Waypoint

A lightweight PHP framework for building APIs and progressively enhanced UIs, with attribute-based
routing and partial HTML views over AJAX.

---

## Overview

Waypoint is for a team that wants **one small framework covering both sides of a typical app** — a JSON
API and a server-rendered, progressively-enhanced UI (partial HTML swaps over AJAX, no separate frontend
build) — without reaching for a full-stack framework's config files, service definitions, or implicit
magic. Routing, dependency injection, validation, and the OpenAPI spec are all driven directly off PHP
attributes on your own classes; there's nothing else to wire up or keep in sync.

It also ships production-grade defaults for the things a real API needs — RFC 9457 structured error
responses, CSRF protection, security headers, request correlation IDs, JWT auth — on by default or a
few lines of `configure()` away, rather than left to every app to reimplement. And it's built to be fast
under real load: attribute discovery compiles to a cache with an opt-in "trust mode" for production,
benchmarked well over an order of magnitude faster than uncached reflection-based discovery.

The core stays small on purpose. Everything beyond routing, DI, views, security and the OpenAPI
generator — translations, LDAP, sessions, rate limiting, exports — is a separate package that plugs
into fixed hooks (see [Plugins](#plugins)), and a shipped [testing kit](#testing) drives real requests
through your app or plugin from PHPUnit.

### Feature highlights

- Attribute-based routing and controllers (`#[Get]`/`#[Post]`/`#[Put]`/`#[Patch]`/`#[Delete]`)
- Simple, reachability-based dependency injection via `#[Inject]` (no separate "service" attribute)
- Request validation attributes (`#[NotBlank]`, `#[Email]`, `#[Length]`, `#[Regex]`, `#[OneOf]`, `#[SameAs]`), read as `$dto->isValid` / `$dto->errors`
- A `FromArray` trait for `#[Body]`-bound DTOs, so their array-hydrating constructor never has to be
  hand-written
- A unified `HttpException` hierarchy with RFC 9457 ("Problem Details for HTTP APIs") JSON error
  responses out of the box, plus an explicit exception-handler registry for anything more specific
- Middleware pipeline built on a mandatory `MiddlewareBase` (`before()`/`after()` hooks, with a
  short-circuit veto), including per-route middleware via `#[Middleware(Class::class)]`
- Built-in CORS support
- Security response headers (`X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, HSTS, opt-in
  CSP) via `SecurityHeadersMiddleware`
- Stateless, double-submit-cookie CSRF protection, with a per-route/controller `#[SkipCsrf]` opt-out
- Request/correlation ID tracking (`X-Request-Id`/`X-Correlation-Id`), automatically stamped onto every
  log line for the duration of that request
- JWT authentication (`useJwt()`), with a required (never-defaulted) signing secret
- An HTML-rendering MVC view layer, with layouts, sections, views in subdirectories, automatic scoped
  CSS/JS per view, `Redirect` results, and the bundled `waypoint.js` client for partial navigation
- Static file serving from a configured public directory
- Gzip response compression, size-thresholded, with an opt-out `#[NoGzip]` attribute per controller/route
- PSR-3 compatible logging (plug in any PSR-3 logger, or use `LoggerOptions::addMono()` for Monolog)
- CLI task runner (`#[Manager]`/`#[Task]`), for the same app to serve HTTP and run background jobs
- A complete OpenAPI 3.1.0 generator (`OpenAPIOptions::$enabled`), driven by a compiled attribute cache
  rather than live reflection
- Optional route/DI compilation caching with an opt-in "trust mode" for production deploys
- A plugin system with fixed hooks (endpoints, route attributes, guards, argument binders, renderers,
  view helpers, client assets), so features ship as packages instead of growing the core
- A testing kit (`Waypoint\Testing`) with a request builder and fluent response assertions

---

## Table of Contents

- [Installation](#installation)
- [Basic Usage](#basic-usage)
- [Routing & Parameter Binding](#routing--parameter-binding)
- [Dependency Injection](#dependency-injection)
- [Middleware](#middleware)
  - [Security Headers](#security-headers)
  - [CSRF Protection](#csrf-protection)
  - [CORS](#cors)
- [Exception Handling](#exception-handling)
- [Views](#views)
  - [Redirects](#redirects)
- [Static Files](#static-files)
- [Gzip Compression](#gzip-compression)
- [JWT Authentication](#jwt-authentication)
- [OpenAPI](#openapi)
- [Request / Correlation ID](#request--correlation-id)
- [Logging](#logging)
- [CLI Tasks](#cli-tasks)
- [Plugins](#plugins)
- [Testing](#testing)
- [Environment Variables](#environment-variables)
- [Route/DI Compilation Caching & Production Performance](#routedi-compilation-caching--production-performance)
- [Options Reference](#options-reference)
- [Contributing](#contributing)
- [License](#license)
- [Contact](#contact)

---

## Installation

Add Waypoint to your project via Composer:

```bash
composer require systonia/waypoint
```

If you want to develop Waypoint locally against a checked-out copy, use a Composer path repository
(with `"symlink": true` on Windows this needs a directory junction, not a real symlink) instead of
the packaged release.

```bash
composer install
composer dump-autoload -o
```

---

## Basic Usage

Create a front controller (typically `./index.php`) that registers your controllers and starts
the app:

```php
<?php
require __DIR__ . '/vendor/autoload.php';

use Waypoint\Waypoint;
use App\Controllers\HelloController;

$app = Waypoint::create();
$app->attach([HelloController::class]);

$app->run();
```

`attach()` discovers every class reachable from your controllers via `#[Inject]` (see
[Dependency Injection](#dependency-injection) below) and wires up the router and container. `run()`
branches on `php_sapi_name()`: under a real
HTTP SAPI it dispatches the current request (`handleHttp()`); under `cli` it runs the CLI task runner
against the process's own `$argv` (`runCli()`) — see [CLI Tasks](#cli-tasks).

A minimal controller:

```php
<?php

namespace App\Controllers;

use Waypoint\Attributes\{Controller, Get, Param};

#[Controller('/hello')]
class HelloController
{
    #[Get('/{name}')]
    public function greet(#[Param] string $name): array
    {
        return ['message' => "Hello, $name!"];
    }
}
```

---

## Routing & Parameter Binding

A route method declares its path with `#[Get]`/`#[Post]`/`#[Put]`/`#[Patch]`/`#[Delete]` — that
attribute *is* the route (there is no separate `#[Route]` attribute). `#[Controller('/prefix')]`
optionally prefixes every route in the class. Parameters are then bound with one of three attributes
depending on where the value comes from:

| Attribute   | Description                                                     | Example                              |
|-------------|-------------------------------------------------------------------|---------------------------------------|
| `#[Param]`  | Binds a route placeholder (e.g. `{customerId}`)                  | `#[Param] string $customerId`         |
| `#[Query]`  | Binds a query string (`?foo=`) parameter                         | `#[Query] string $search`             |
| `#[Body]`   | Binds the request body (JSON or form) to a DTO                   | `#[Body] UserDTO $user`               |

An unattributed scalar-typed parameter (`string`/`int`/`float`/`bool`) is bound implicitly: Waypoint
tries the route placeholder first, then falls back to the query string. `Request`/`Response`
type-hinted parameters (or properties) are injected directly, no attribute needed.

```php
use Waypoint\Attributes\{Get, Post, Param, Query, Body};

class UserController
{
    #[Get('/{customerId}')]
    public function getProfile(
        #[Param] string $customerId,          // from /{customerId}
        #[Query] ?string $expand = null,       // from ?expand=...
    ) {
        // ...
    }

    #[Post('/{customerId}')]
    public function updateProfile(
        #[Param] string $customerId,
        #[Body] UserProfileUpdateDTO $payload  // hydrated from the JSON/form body
    ) {
        // ...
    }
}
```

By default `#[Param]`/`#[Query]` match the parameter's own name; pass a name to bind a
differently-named placeholder or query key: `#[Param('uuid')] string $userId`, `#[Query('q')] string
$queryTerm`.

### Array/collection request bodies

`#[Body]` normally requires the parameter to be typed as a DTO class. For an endpoint whose body is a
JSON array of objects, type the parameter `array` and give `#[Body]` an `of:` element class instead —
each element is hydrated into (and validated against) that DTO individually:

```php
use Waypoint\Attributes\{Post, Body};

class OrdersController
{
    #[Post('/bulk')]
    public function bulkCreate(#[Body(of: LineItemDTO::class)] array $items): array
    {
        // $items is LineItemDTO[]; a validation failure on any element throws
        // ValidationException with per-index error details (e.g. "1" => [...]).
        return ['count' => count($items)];
    }
}
```

This is reflected in the generated OpenAPI schema too: the request body documents as
`{"type": "array", "items": {"$ref": "#/components/schemas/LineItemDTO"}}` rather than a single `$ref`.

### Validation

DTOs used with `#[Body]` can carry validation attributes on their public properties:

```php
use Waypoint\Attributes\{NotBlank, Email, Length, Regex, OneOf, SameAs};

class CreateProductDTO
{
    #[NotBlank]
    public string $name = '';

    #[Email]
    public ?string $contactEmail = null;

    #[Length(min: 2, max: 12)]
    #[Regex(pattern: '/^[A-Z0-9\-]+$/')]
    public string $sku = '';

    #[OneOf(['draft', 'live'])]
    public string $status = 'draft';

    public string $password = '';

    #[SameAs('password')]
    public string $passwordConfirm = '';
}
```

`#[OneOf]` compares strictly (`'1'` is not `1`) and, like `#[Email]`, skips `null`; `#[SameAs]` compares
against the named property of the same DTO.

A failing DTO throws `ValidationException`, which the default exception handler turns into a `422`
RFC 9457 Problem Details response, with per-field errors under `errors`:
`{"type": "about:blank", "title": "Validation failed", "status": 422, "detail": "Validation failed", "errors": {"sku": "..."}}`
— see [Exception Handling](#exception-handling). The messages are English by default; a plugin can bind
`Waypoint\Validation\Messages` to translate them (the `waypoint-i18n` plugin does, see [Plugins](#plugins)).

### Hydrating DTOs (`FromArray`)

`#[Body]`/`#[Body(of: ...)]` construct your DTO directly as `new YourDTO($req->body())` (see
[Array/collection request bodies](#arraycollection-request-bodies) above), so every DTO used that way
needs a constructor that hydrates its public properties from an array. `use Waypoint\FromArray;` provides
exactly that, instead of writing (and keeping in sync across every DTO) the same `foreach`/`property_exists`
loop by hand:

```php
use Waypoint\FromArray;
use Waypoint\Attributes\{NotBlank, Email};

class CreateProductDTO
{
    use FromArray;

    #[NotBlank]
    public string $name = '';

    #[Email]
    public ?string $contactEmail = null;
}
```

Unknown keys in the input array are silently ignored. A property that must never be settable this way
(e.g. a server-computed or nested value — `#[Body]` hydration is flat, so assigning a raw array straight
to a typed object property would `TypeError` instead of recursively hydrating it) can be excluded by
overriding `fromArrayExcludes()`:

```php
use Waypoint\FromArray;

class ProductDTO
{
    use FromArray;

    public ?ProductDTO $relatedProduct = null; // set server-side, never from client input

    /** @return array<int, string> */
    protected function fromArrayExcludes(): array
    {
        return ['relatedProduct'];
    }
}
```

A DTO that needs extra logic around hydration (not just the plain loop) can declare its own
`__construct()` — which overrides the trait's, same as any other method — and call
`$this->hydrateFromArray($data)` from it directly.

#### Validating by hand (`$dto->isValid`, `$dto->errors`, `fail()`)

Outside `#[Body]` — an HTML form handler that re-renders the page with the errors instead of a 422 — the
DTO validates itself. `isValid` and `errors` are computed properties: every read applies the attribute
rules, so there is no validator to construct and no moment where the result is stale. `fail()` adds a
failure only your code can know about (a taken email, a wrong current password) to the same list:

```php
$data = new RegisterDTO($req->body());
if ($data->isValid && $this->users->findByEmail($data->email)) {
    $data->fail('email', 'That email is already registered.');
}
if (!$data->isValid) {
    return $this->registerView($req, error: implode(' ', $data->errors));
}
```

`errors` is `field => message`, attribute failures first and `fail()` entries after them; a later `fail()`
for the same field replaces the earlier one. Input keys named `errors`, `isValid` or `failures` are never
hydrated, and the two computed properties never appear in OpenAPI schemas or redacted logs.

---

## Dependency Injection

Waypoint uses a simple, reachability-based dependency injection system: use `#[Inject]` on a property (or
constructor parameter) to declare a dependency. The container discovers what to instantiate by walking
`#[Inject]`-typed properties/parameters starting from your registered controllers — any class reachable
that way is auto-instantiated and shared as a singleton. **There is no separate "mark this class as a
service" attribute** — reachability via `#[Inject]` is what registers a class.

```php
<?php

namespace App\Services;

use Waypoint\Attributes\Inject;
use Waypoint\Logger;
use App\Entities\User;

class UserService
{
    #[Inject]
    private Logger $logger;

    public function getUser(string $name): ?User
    {
        $user = User::where('name', $name)->first();
        if (!$user) {
            $this->logger->error("user not found");
        }
        return $user;
    }
}
```

```php
#[Controller('/users')]
class UserController
{
    #[Inject]
    private UserService $users;

    #[Get('/{name}')]
    public function show(#[Param] string $name): array
    {
        return $this->users->getUser($name)?->toArray() ?? [];
    }
}
```

Notes:

- A class only gets auto-registered if it's reachable via `#[Inject]` from a controller you registered
  (directly, or transitively through another injected service).
- `#[Inject]` also works on middleware classes registered via `#[Middleware]` (see below).
- Container-managed classes are constructed with no arguments and populated purely through
  `#[Inject]`-marked properties — there is currently no constructor-argument resolution; a
  constructor parameter marked `#[Inject]` is *discovered* (so its type is pulled into the container
  graph) but not itself resolved/passed as a constructor argument. Prefer property injection.

---

## Middleware

Global middleware runs on every request, registered via `use()` with a plain callable:

```php
$app->use(function ($req, $res, $next) {
    // ... before
    $result = $next($req, $res);
    // ... after
    return $result;
});
```

`useCors()` and `useJwt()` (see below) are both just built-in middleware registered this way.

Per-route middleware is declared with `#[Middleware(Class::class)]` directly on a controller method
(repeatable). **The class must extend `Waypoint\Http\MiddlewareBase`** — any `#[Middleware(...)]` class
that doesn't is silently skipped at compile time (the same treatment a nonexistent class already gets),
so this isn't optional. `MiddlewareBase::handle()` is `final` and always runs `before()` → `$next()` →
`after()`, in that fixed order; override `before()`/`after()` instead of reimplementing that plumbing
yourself:

```php
use Waypoint\Http\{MiddlewareBase, Request, Response};
use Waypoint\Exceptions\ForbiddenException;

class RequireAdminMiddleware extends MiddlewareBase
{
    protected function before(Request $req, Response $res): bool
    {
        if (($req->jwt['role'] ?? null) !== 'admin') {
            throw new ForbiddenException('Admin access required.');
        }
        return true; // false vetoes: $next() and after() are both skipped
    }
}
```

```php
use Waypoint\Attributes\{Get, Middleware};

class AdminController
{
    #[Get('/dashboard')]
    #[Middleware(RequireAdminMiddleware::class)]
    public function dashboard(): array
    {
        return ['ok' => true];
    }
}
```

`before()` returning `false` is the veto point: `$next()` (and `after()`) are skipped entirely and the
chain short-circuits there — typically because `before()` already threw, or built its own response.
`before()`/`after()` are both no-ops by default, so a subclass only overrides whichever one it needs. The
class is resolved through the container at dispatch time — so it can itself use `#[Inject]` — rather than
being instantiated directly.

Because `MiddlewareBase` also implements `__invoke()` (delegating to `handle()`), any subclass is
directly usable on the `$app->use()` pipe too, without wrapping it in a closure:

```php
$app->use(new SecurityHeadersMiddleware()); // see below
```

### Security Headers

`SecurityHeadersMiddleware` fills in the standard hardening response headers — `X-Content-Type-Options`,
`X-Frame-Options`, `Referrer-Policy`, and (HTTPS requests only) `Strict-Transport-Security` — enabled by
default with sensible values; `Content-Security-Policy` is opt-in, since a wrong one-size-fits-all CSP
would break real pages rather than just fail open. Register it once, via `use()`:

```php
use Waypoint\Http\SecurityHeadersMiddleware;
use Waypoint\Options\SecurityHeaderOptions;

$app->configure(function (SecurityHeaderOptions $opts) {
    $opts->frameOptions = 'SAMEORIGIN';       // default: 'DENY'
    $opts->cspEnabled = true;                 // default: false
    $opts->csp = "default-src 'self'";        // required once cspEnabled is true
});

$app->use(new SecurityHeadersMiddleware());
```

Every header is only *filled in* (via `Response::hasHeader()`) — a controller (or another middleware)
that already set one of these headers itself always wins, regardless of where this sits in the pipe.

### CSRF Protection

Stateless, double-submit-cookie CSRF protection for state-changing requests — no server-side session
store: a token is a self-contained, HMAC-signed `{exp, nonce}` pair, verified against
`CsrfOptions::$secret`. Configuring a secret is what opts an app into it at all — unconfigured (the
default), every request passes through unchecked, so this never breaks an app that doesn't use it:

```php
use Waypoint\Options\CsrfOptions;

$app->configure(function (CsrfOptions $opts) {
    $opts->secret = $env->get('CSRF_SECRET'); // required to actually enable checking
    $opts->cookieSecure = true;               // default -- HTTPS only; set false for local HTTP dev
});
```

Once a secret is set, every `POST`/`PUT`/`PATCH`/`DELETE` request is checked automatically (`GET`/`HEAD`
never carry a body/side effect worth protecting) — no middleware to register. A token is issued as a
cookie (`CsrfOptions::$cookieName`, default `csrf_token`) on every rendered `View` (see
[Views](#views)), and must be repeated back by the client either as a request header
(`CsrfOptions::$headerName`, default `X-CSRF-Token` — the AJAX/`fetch()` path) or a body field
(`CsrfOptions::$fieldName`, default `_csrf` — a classic no-JS `<form>`). A mismatch, missing token, or
expired token throws `ForbiddenException` (403).

Inside a view template, `$this->csrf` renders the classic no-JS `<form>` half of that pattern:

```php
<form method="post">
    <?= $this->csrf->field() ?>  <!-- hidden input -->
</form>
```

The AJAX/`fetch()` half needs no app code at all if you're loading
[`waypoint.js`](#views) (see `$this->waypointJsTag()` in [Views](#views)): it patches `fetch()`
itself so every same-origin `POST`/`PUT`/`PATCH`/`DELETE` call automatically carries the header, reading
the token straight from the cookie. A plain `fetch()` call needs to know nothing about CSRF:

```js
fetch('/orders', { method: 'POST', body: JSON.stringify(data) }); // X-CSRF-Token attached automatically
```

Not using `waypoint.js`? Read the cookie and attach the header yourself the same way it does —
`Waypoint.csrf.token()` is also there directly, for a non-`fetch()` use (a WebSocket handshake, a
manually-built `XMLHttpRequest`). If `CsrfOptions` itself was reconfigured away from its default cookie/
header names, `$this->waypointJsTag()` passes the new names to `waypoint.js` automatically as
`data-csrf-cookie`/`data-csrf-header` on the `<script>` tag — nothing to do in the template.

Opt a token-auth-only JSON API (or any route with no double-submit cookie to check) out entirely with
`#[SkipCsrf]`, on the controller class or a single method:

```php
use Waypoint\Attributes\SkipCsrf;

#[Controller('/api')]
#[SkipCsrf] // every route below skips CSRF verification
class ApiController
{
}
```

### CORS

Configure `CorsOptions`, then just enable the feature — `useCors()` takes no parameters:

```php
use Waypoint\Options\CorsOptions;

$app->configure(function (CorsOptions $opts) {
    $opts->allowOrigin = 'https://yourdomain.com';
    $opts->allowMethods = 'GET, POST, OPTIONS';
    $opts->allowHeaders = 'Content-Type, Authorization';
    $opts->allowCredentials = true;
    $opts->maxAge = 86400;           // optional: cache preflight for a day
    $opts->exposeHeaders = 'ETag';   // optional
});

$app->useCors();
```

Leaving a property unset omits its header entirely — CORS is off by default. `allowCredentials` is a
plain `bool`: there's no meaningful `false` value for `Access-Control-Allow-Credentials` per the Fetch
spec (it's either present and exactly `"true"`, or simply absent), so unlike the others it can't end up
sending a blank/malformed header value.

`OPTIONS` requests are answered directly with a `204` once the CORS headers are attached.

---

## Exception Handling

`Waypoint\Exceptions\HttpException` is the base class every one of Waypoint's own HTTP-facing exceptions
extends (`ForbiddenException`, `UnauthorizedException`, `NotFoundException`, `ValidationException`), and
the one your own domain exceptions should extend too. Its fields map directly onto
[RFC 9457](https://www.rfc-editor.org/rfc/rfc9457) ("Problem Details for HTTP APIs"):

```php
use Waypoint\Exceptions\HttpException;

class OutOfStockException extends HttpException
{
    public function __construct(string $detail)
    {
        parent::__construct(statusCode: 409, title: 'Conflict', detail: $detail);
        // $type (default 'about:blank') and $instance are also constructor args, if you need them
    }
}
```

Waypoint's default handler (installed automatically, no setup) catches every `HttpException` and builds a
`Content-Type: application/problem+json` response, status = `$statusCode`, body =
`{type, title, status, detail?, instance?}`:

```json
{"type": "about:blank", "title": "Conflict", "status": 409, "detail": "'Sourdough Loaf' is out of stock."}
```

A subclass with extra structured data overrides `toProblemDetails()` to add its own members alongside the
five standard ones — `ValidationException` does exactly this, adding `errors` (the per-field/per-index
messages):

| Exception                | Status | Extra body member |
|---------------------------|--------|--------------------|
| `ValidationException`     | 422    | `errors` (per-field/per-index messages) |
| `ForbiddenException`      | 403    | — |
| `UnauthorizedException`   | 401    | — |
| `NotFoundException`       | 404    | — |
| Any other `Throwable`     | 500    | — (message never exposed, not even in development — see below) |

A non-`HttpException` `Throwable` (anything unexpected/unhandled) maps to a generic 500 Problem Details
body with a fixed `"Internal Server Error"` title — **never** `$e->getMessage()` or a stack trace, in any
environment: an unexpected exception's message can easily contain internal details (a file path, a query,
a raw driver error) never meant to reach whoever triggered the crash, so `Environment::isDev()` alone
isn't treated as a safe enough gate for that. It's still logged in full via the container's `Logger`. A
project that wants more than the generic message needs its own explicit
`useExceptionHandler(Throwable::class, ...)` override — an actual opt-in, not an implicit one.

Register your own handler — for an exact exception class, or anything further up its class/interface
hierarchy — with `useExceptionHandler()`. The most specific registered handler wins: exact class match
first, then parent classes, then implemented interfaces, then the `Throwable` catch-all.

```php
use Waypoint\Http\{Request, Response};

$app->useExceptionHandler(MyDomainException::class, function (MyDomainException $e, Request $req, Response $res) {
    $res->status(409)
        ->withHeader('Content-Type', 'application/json')
        ->write(json_encode(['error' => $e->getMessage()]));
    // no need to call ->send() -- see below
});
```

Calling `useExceptionHandler()` again for the same class overrides the previous handler — including
the built-in `HttpException`/`Throwable` defaults above.

A handler only needs to build `$res` (status/headers/body) and return — `App::handleHttp()` sends the
response exactly once, itself, after the whole middleware pipe has unwound. `Response::send()` stays
idempotent for compatibility — a handler that calls it itself doesn't break anything — but it's no longer
the recommended shape for a new handler.

---

## Views

Return `Waypoint\Http\View` from a route method instead of an array to render server-side HTML, with
layouts, named sections, and per-view/per-layout scoped CSS/JS:

```php
use Waypoint\Http\View;

#[Get('/products/{id}')]
public function show(#[Param] string $id, Request $req): View
{
    $product = Product::find($id);
    return new View('ProductDetail', $product, partial: $req->acceptPartial);
}
```

Configure where views/layouts live via `RendererOptions`:

```php
use Waypoint\Options\RendererOptions;

$app->configure(function (RendererOptions $opts) {
    $opts->directory = __DIR__ . '/../views';
    $opts->layout = '_Layout.php'; // default layout, per-View override via the 4th constructor argument
});
```

`$opts->layout` defaults to `'_Layout'` with **no extension**, which never matches a real file — pass an
explicit `.php` name, or no layout is applied and every render falls back to the bare view content.

Inside a view/layout `.php` file, `$model` is whatever was passed to `View`'s constructor (commonly an
array, e.g. `$model['product']`), and `$this` is the `View` instance itself:

- `$this->startSection('name')` / `$this->endSection()` / `$this->section('name', required: false)` —
  named content blocks a layout pulls in, e.g. for per-page `<head>` scripts.
- `$this->assetTags()` / `$this->layoutAssetTags()` — `<link>`/`<script>` tags for the current view's own
  sibling `.css`/`.js` (`ProductDetail.css` next to `ProductDetail.php`), and for the layout's own, if
  either has one.
- `$this->scopeAttribute()` / `$this->layoutScopeAttribute()` — a `data-view="..."` attribute for the
  element that content should be scoped to; the matching CSS is automatically wrapped in a
  `[data-view="..."]` nesting selector, so plain rules in `ProductDetail.css` only ever apply where that
  attribute is present.

The client half, `waypoint.js` (partial navigation via `wp-target`, form enhancement, automatic CSRF
headers), ships inside the package and is compiled into the same asset pipeline as view CSS/JS: served at
`{assetsPath}/waypoint.{hash}.js` with immutable caching, rebuilt when the framework is upgraded. The
layout emits the tag with `<?= $this->waypointJsTag() ?>`; when `CsrfOptions` was changed from its
defaults the tag also carries `data-csrf-cookie`/`data-csrf-header` for the client, so nothing is
duplicated in the template.

A view or layout's sibling `.css`/`.js` is discovered automatically (same basename, same directory,
either role) — nothing to register. Views can live in subdirectories: `views/Admin/Users.php` is
`new View('Admin/Users')`, always `/`-separated, and its `Admin/Users.css` is found, scoped
(`[data-view="Admin/Users"]`) and announced under that same name. A name that starts with `/` or
contains a `..` segment is rejected at construction, so a view name built from request input can never
reach outside the views directory. The layout is still resolved from the root of the directory only.
Compiled, content-hashed copies are written under
`{cacheDirectory}/assets/` (see [Route/DI Compilation Caching](#routedi-compilation-caching--production-performance)
below) and served through `GET /assets/{hash}.css`/`.js` with a year-long, immutable `Cache-Control`
header.

Passing `partial: true` (typically driven by a request header your client sets — `$req->acceptPartial`
is true when the request carried `X-Waypoint-Accept: partial`) skips the layout
entirely and renders just the view. The response also carries `X-Waypoint-View-Name`/`-Css`/`-Js`
headers describing what was rendered, for a client-side router to apply without a full page reload.

### Redirects

Return `Waypoint\Http\Redirect` to send a redirect instead of a body — typically from a form `POST`
that succeeded (POST/redirect/GET), next to the `View` the same handler renders when it didn't:

```php
use Waypoint\Http\{Redirect, Request, Response, View};

#[Post('/login')]
public function loginSubmit(Request $req, Response $res): View|Redirect
{
    if (!$this->credentialsValid($req)) {
        return new View('Login', ['error' => 'Invalid email or password.']);
    }
    $res->withCookie('auth_token', $token);
    return new Redirect('/dashboard');
}
```

`new Redirect($location, $status = 302)` accepts any `3xx` (e.g. `303` for an explicit See Other, `301`/`308`
for a permanent move, `307` to preserve the original method). The response is just the status and a
`Location` header — no body, and the route's formatter is ignored — while anything the handler already
queued on the `Response` (cookies, headers) is kept. A `$location` that is empty or contains a line break
is rejected at construction. A redirect is an ordinary result, not an exception: the cases that *should*
redirect on failure (no/expired JWT on an `#[Authenticated]` route) already go through
`JWTOptions::$loginRedirectUrl` — see [JWTOptions](#jwtoptions).

---

## Static Files

Serve a plain public directory (images, fonts, a pre-built frontend bundle, etc.) by configuring
`FileSystemOptions::$publicDirectory` — checked before routing, so a matching file always wins over a
route at the same path:

```php
use Waypoint\Options\FileSystemOptions;

$app->configure(function (FileSystemOptions $fs) {
    $fs->publicDirectory = __DIR__ . '/../public';
});
```

Left unset (the default), no static directory is served at all. Requests are resolved with `realpath()`
plus a containment check, so a path can never escape `publicDirectory` — `/../`-style traversal 404s
instead of serving anything outside it. `.css`/`.js`/`.mjs`/`.json`/`.svg`/`.html` get a fixed
`Content-Type`; anything else falls back to `mime_content_type()`, then `application/octet-stream`.

---

## Gzip Compression

Every response funnels through `Response::send()`, which gzips the body when the client's
`Accept-Encoding` header includes `gzip` and the body meets a configurable size threshold — compressing
a tiny response is a net loss once gzip's own framing overhead is counted, so anything shorter than
`CompressionOptions::$minBytes` (default `1024` bytes) is always sent uncompressed. On by default, no
setup required:

```php
use Waypoint\Options\CompressionOptions;

$app->configure(function (CompressionOptions $opts) {
    $opts->minBytes = 2048; // default: 1024
    $opts->enabled = false; // default: true — turns compression off everywhere
});
```

A compressed response gets `Content-Encoding: gzip` and `Vary: Accept-Encoding`; `Content-Length` always
reflects the actual (possibly compressed) bytes being sent. A response that already carries its own
`Content-Encoding` is left alone rather than double-encoded.

Opt individual routes out with `#[NoGzip]`, on the controller class (every route on it) or a single
method (just that route):

```php
use Waypoint\Attributes\{Controller, Get, NoGzip};

#[Controller('/downloads')]
#[NoGzip] // every route below skips compression
class DownloadsController
{
    #[Get('/report.csv')]
    #[NoGzip] // equivalent here, since the whole class already opts out
    public function report(): string
    {
        // ...
    }
}
```

---

## JWT Authentication

Configure `JWTOptions` before calling `useJwt()`. **The signing secret has no default** — it's an
uninitialized typed property, so using it unconfigured throws immediately rather than silently signing
tokens with a guessable value:

```php
use Waypoint\Options\JWTOptions;

$app->configure(function (JWTOptions $opts, Environment $env) {
    $opts->secret = $env->get('JWT_SECRET'); // required -- no default
    $opts->alg = 'HS256';                // default
    $opts->ttl = 3600;                   // default, seconds
    $opts->tokenType = 'Bearer';         // default
    $opts->header = 'Authorization';     // default
    $opts->cookieName = null;            // default (disabled) -- see below
});

$app->useJwt();
```

Once enabled, a valid token's decoded payload is available as `$req->jwt` in any controller/middleware
that receives the `Request`.

A plain browser page navigation never sends a bearer `Authorization` header — only an explicit
`fetch()`/XHR call that sets one itself does. Set `JWTOptions::$cookieName` to also read the token back
out of a same-named cookie whenever the header didn't already produce one, so a signed-in session
(e.g. one your login endpoint issued via `Set-Cookie: <name>=<token>; HttpOnly`) survives a full page
load too:

```php
$app->configure(function (JWTOptions $opts) {
    $opts->secret = $_ENV['JWT_SECRET'];
    $opts->cookieName = 'auth_token';
});
```

Issue that cookie from your own login endpoint with `Response::withCookie()` (HttpOnly and
`SameSite=Lax` by default), and clear it on logout with `withoutCookie()`:

```php
#[Post('/login')]
public function login(#[Body] LoginDTO $data, Response $res): array
{
    $token = JWT::encode(['sub' => $userId]);
    $res->withCookie('auth_token', $token); // maxAge: null -> a session cookie
    return ['accessToken' => $token];        // also usable as a Bearer token directly
}

#[Post('/logout')]
public function logout(Response $res): array
{
    $res->withoutCookie('auth_token');
    return ['loggedOut' => true];
}
```

---

## OpenAPI

Switch the endpoint on via `OpenAPIOptions` (nothing to attach):

```php
$app->configure(function (OpenAPIOptions $opts) {
    $opts->enabled = true;
    $opts->path = '/openapi'; // default
});
```

- `GET /openapi/spec.json` — the generated OpenAPI 3.1.0 document
- `GET /openapi/spec.{version}.json` — one `#[Version]`'s document (404 for an unknown version)
- `GET /openapi/swagger.html` — bundled Swagger UI

The generator is driven entirely by a compiled attribute cache (see
[Route/DI Compilation Caching](#routedi-compilation-caching--production-performance)) rather than live
reflection on every request, so serving the spec is cheap even under trust mode. It documents:

- Path/query parameters with their real PHP types (`#[Param]`/`#[Query]`, and unattributed scalars)
- `#[Body]` DTO request bodies, including nested and self-/mutually-referencing (cyclic) models
- `#[Body(of: ...)]` array/collection request bodies (see [above](#arraycollection-request-bodies))
- `#[Summary]`, `#[Tags]`, `#[Throws]`, `#[Deprecated]`, `#[Ignore]`
- `#[Schema(name: ...)]` to override a model's generated component name
- `#[Property(description:, format:, example:, deprecated:)]` to annotate individual model properties
- Security schemes configured via `OpenAPIOptions::$securitySchemes`/`$security`

```php
use Waypoint\Options\OpenAPIOptions;

$app->configure(function (OpenAPIOptions $opts) {
    $opts->title = 'My API';
    $opts->version = '1.2.0';
    $opts->servers = [['url' => 'https://api.example.com']];
});
```

---

## Request / Correlation ID

Every request gets a correlation id, available as `$req->id` in any controller/middleware that receives
the `Request`: the incoming `X-Request-Id` header if the client/upstream sent one (`X-Correlation-Id` as a
fallback header name — an upstream proxy's own, possibly non-UUID, id is exactly as valid a correlation id
as one Waypoint would generate itself), otherwise a fresh UUID v4. No configuration or middleware
required.

The resolved id is mirrored back to the client as an `X-Request-ID` response header, and automatically
stamped onto every `Logger` call made during that request, under the `request_id` context key — no need
to pass it around by hand:

```php
#[Get('/{id}')]
public function show(#[Param] string $id, Request $req): array
{
    $this->logger->info('Fetching widget', ['id' => $id]);
    // logged with 'request_id' => $req->id automatically added to context
    // ...
}
```

---

## Logging

Waypoint's `Logger` is PSR-3 based: register any `Psr\Log\LoggerInterface` implementation, optionally
filtered to specific levels. Registration happens on `LoggerOptions`, configured like any other Options
class via `configure()`; `Logger` itself is a stateless, container-resolvable service that dispatches to
whatever `LoggerOptions` currently holds -- inject it with `#[Inject]` into a controller/service:

```php
use Waypoint\Options\LoggerOptions;
use Psr\Log\LogLevel;

$app->configure(function (LoggerOptions $opts) {
    $opts->add($myPsr3Logger, [LogLevel::ERROR, LogLevel::CRITICAL]);

    // Or Monolog directly, without wrapping it yourself:
    $opts->addMono('app', new \Monolog\Handler\StreamHandler('php://stdout'), [LogLevel::INFO]);
});
```

```php
use Waypoint\Attributes\{Controller, Get, Inject};
use Waypoint\Logger;

#[Controller('/orders')]
class OrdersController
{
    #[Inject]
    private Logger $logger;

    #[Get('/{id}')]
    public function show(string $id): array
    {
        try {
            // ...
        } catch (\Throwable $e) {
            $this->logger->error('Something went wrong', ['exception' => $e]);
            throw $e;
        }
    }
}
```

---

## CLI Tasks

The same front controller can serve HTTP *and* run background/maintenance jobs: under the `cli` SAPI,
`$app->run()` (or `$app->runCli()`, which returns an exit code instead of calling `exit()`) reads the
process's own `$argv` and dispatches to a `#[Task]`-attributed method instead of routing an HTTP
request.

```php
use Waypoint\Attributes\{Manager, Task};

#[Manager('users')]
class UserTasks
{
    #[Task('sync')]
    public function sync(): string
    {
        // ...
        return "Synced.\n";
    }
}
```

```php
$app->attach([UserTasks::class]);
$app->run();
```

```bash
php index.php users:sync
```

---

## Plugins

Anything beyond the core (translations, LDAP, sessions, rate limiting, ...) is a separate Composer
package registered with one line before `attach()`:

```php
$app->plugin(new Waypoint\I18n\I18nPlugin());
$app->configure(function (Waypoint\I18n\I18nOptions $opts) { ... });
$app->attach([...]);
```

A plugin is one class implementing `Waypoint\Plugin\Plugin` (extend `PluginBase` for the defaults). It
declares what it adds and does nothing else:

| Declares | Effect |
|---|---|
| `classes()` | Controllers and `#[Manager]` classes attached alongside the app's own. |
| `middlewares()` | App-level middlewares, added at `attach()`. |
| `boot(Container)` | Runs once at `attach()`: bind services, read its Options class. |
| `cacheInputs()` | `{path => mtime}` of files whose change must rebuild the route cache. |
| `hooks()` | Objects implementing any of the hook interfaces below. |

| Hook (`Waypoint\Plugin\...`) | When | Typical use |
|---|---|---|
| `Endpoint` | Before routing, after static files. | Health, metrics, an SSO callback. |
| `RouteAttributeCompiler` | At compile time, per route; result cached under the plugin's name. | A `#[RateLimit]` attribute. |
| `Guard` | Per request before the controller, after auth/CSRF; gets that route's compiled data (`[]` if none). | Enforce the attribute. |
| `ResponseHook` | After the result was rendered. | Audit, metrics, idempotency store. |
| `ArgumentBinder` | Own parameter attributes/types for controller methods, asked after the core attributes and before the implicit scalar binding. | `#[File]`, a table request. |
| `Renderer` | Own return types, asked after `View`/`Redirect`. | Exports, PDFs. |
| `ViewHelper` | Methods templates can call as `$this->name()`. | `$this->t('key')`. |
| `ClientAsset` | CSS/JS files shipped by the plugin, served content-hashed; render with `$this->pluginAssetTags()`. | A table component. |

Rules: `requires()` names plugins that must come first; two plugins with the same `name()` or the
same view helper are a boot error; plugins never reach into each other or into the core's internals.
The core also exposes `Container::bind(Interface::class, $service)` and `Waypoint\Validation\Messages`
(translates the validator's English messages, which double as catalog keys) for plugins to hook into.

Available plugins:

| Package | Adds |
|---|---|
| `systonia/waypoint-i18n` | `$this->t('Source text')` with JSON catalogs kept by CLI tasks, per-request locale, translated validation errors. |
| `systonia/waypoint-flash` | Flash messages across a redirect, shown once, with and without JavaScript; the reference for the client plugin API. |
| `systonia/waypoint-charts` | Bar and line charts as server-rendered SVG with a data table; `$this->chart($chart)` in views or `return $chart;` as `image/svg+xml`. |

---

## Testing

`Waypoint\Testing\TestCase` (needs `phpunit/phpunit` as a dev dependency) drives a real request through
the app the way a SAPI would, with a fresh `Waypoint` and clean superglobals per test:

```php
use Waypoint\Testing\TestCase;

final class LoginTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $app = $this->app();          // Waypoint::create()
        $app->configure(...);
        $app->attach([PagesController::class]);
    }

    public function testWrongPasswordStaysOnTheLoginPage(): void
    {
        $this->post('/login', ['email' => 'a@b.de', 'password' => 'wrong'])
            ->assertOk()
            ->assertSee('Invalid email or password');
    }

    public function testDashboardNeedsALogin(): void
    {
        $this->request('GET', '/dashboard')->asNavigation()->send()->assertRedirect('/login');
        $this->request('GET', '/dashboard')->withJwt(['sub' => '1'])->send()->assertOk();
    }
}
```

- Requests: `get()`, `post()` (form fields), `json()` (raw JSON body), or `request()` for the builder:
  `withHeader()`, `withCookie()`, `withJwt($payload)`, `withCsrf()` (a valid cookie + header pair),
  `asPartial()` (what waypoint.js sends), `asNavigation()` (what a browser sends), then `send()`.
- Responses: `status()`, `header()`, `cookie()`, `body()`, `json('errors.email')`, and fluent
  `assertStatus/Ok/Redirect/Header/HeaderMissing/ContentType/Cookie/See/DontSee/Body/Json/JsonPath`.
- `tempDir()` gives a directory that is removed after the test, e.g. for `FileSystemOptions::$cacheDirectory`.

The framework's own suite and every plugin run on this kit.

---

## Environment Variables

`Waypoint\Environment` reads env vars/`.env`/JSON config files through whatever `EnvironmentOptions` the
container holds -- resolved through the container like any other service, so it's `#[Inject]`-able into
a controller/service. `get($key)`/`isDev()` lazily call `load()` on first use if nothing has been loaded
yet. `load(?string $dir = null)` reads, from `$dir`, each of these in order -- later ones overlay (add
to/override) earlier ones:

1. System env vars, then `$_ENV`, then `$_SERVER`
2. `.env`, then `.env.$APP_ENV`
3. `config.json`, then `config.$APP_ENV.json`
4. `$opts->localConfigFile` (default `config.local.json`) — see below

`$dir` **defaults to the current working directory** — the project root, for how PHP is normally invoked
(`php -S ... -t public`, `composer`/CLI tasks, most process managers) — not this file's own location.
Call it explicitly with an absolute path if your entry point's cwd isn't reliable in your deployment,
either directly or via `configure()`:

```php
use Waypoint\Options\EnvironmentOptions;

$app->configure(function (EnvironmentOptions $opts) {
    $opts->load(__DIR__ . '/..'); // e.g. from public/index.php, project root is one level up
});
```

```php
use Waypoint\Attributes\{Controller, Get, Inject};
use Waypoint\Environment;

#[Controller('/auth')]
class AuthController
{
    #[Inject]
    private Environment $env;

    #[Get('/config')]
    public function config(): array
    {
        return ['issuer' => $this->env->get('JWT_ISSUER')];
    }
}
```

`APP_ENV` defaults to `development` under the `cli`/`cli-server` SAPI when unset anywhere;
`Environment::isDev()` gates whether unhandled-exception messages are exposed in the default 500
response.

The JSON files are flat `{"KEY": value, ...}` objects (a value keeps its JSON type — bool/int/array/etc,
unlike `.env`'s always-string values). `config.json`/`config.$APP_ENV.json` are meant to be committed
(safe defaults, or demo/dev-only values); `$opts->localConfigFile` is meant to be **gitignored** — a
per-developer override for real secrets or local-only values, read last so it wins over everything else,
without having to write real system/shell env vars just to keep them out of version control:

```php
$app->configure(function (EnvironmentOptions $opts) {
    $opts->localConfigFile = 'config.mine.json'; // default: 'config.local.json'
    // or: $opts->localConfigFile = null;        // disable this layer entirely
});
```

Missing files at any layer are silently skipped — nothing is required to exist.

---

## Route/DI Compilation Caching & Production Performance

`attach()` normally discovers routes/DI/attributes via Reflection on every boot. `FileSystemOptions`,
configured like any other Options class via `configure()`, compiles that once to disk instead:

```php
use Waypoint\Options\FileSystemOptions;

$app->configure(function (FileSystemOptions $fs) {
    $fs->cacheDirectory = __DIR__ . '/var/cache';

    // Re-validates the cache (reflects every controller, compares file mtimes)
    // on every request -- safe default, but costs a Reflection pass per boot.
    $fs->cacheValidate = true;

    // "Trust mode": skips validation entirely and just reads the compiled
    // cache. You are responsible for clearing/rebuilding this directory on
    // deploy -- the same tradeoff Symfony's/Laravel's own prod caching makes.
    // $fs->cacheValidate = false;
});
```

Trust mode reads the compiled route/service/attribute data once per request (shared between the
router and the DI container setup) instead of re-deriving it via Reflection, and skips the file-mtime
freshness check — this is the single largest throughput lever available: benchmarked well over an
order of magnitude faster than uncached Reflection-based discovery under repeated requests.

The cache directory holds `routes.php` (route and task plans, the service list, plugin route data),
`attributes.php` (every controller attribute, for the OpenAPI generator), `meta.php` (the mtimes the
cache was built from) and `assets/` (content-hashed view CSS/JS, `waypoint.js`, plugin assets). With
`cacheValidate = true` the cache is rebuilt as soon as any controller file, view asset, plugin
`cacheInputs()` entry or the client bundle changes, or a controller is added or removed; in trust mode
you clear the directory on deploy.

**Also enable OPcache** (`opcache.enable=1`, and `opcache.enable_cli=1` if you're benchmarking or
running under `php -S`) in any environment where you care about request latency — across every PHP
framework we benchmarked Waypoint against (Symfony, Laravel, Slim), OPcache being off was consistently
the single dominant cost, well beyond anything framework-specific. Trust-mode caching and OPcache are
complementary: OPcache skips re-parsing/re-compiling PHP source on every request; trust mode skips
Waypoint's own Reflection-based discovery on top of that.

---

## Options Reference

Every `Waypoint\Options\*` class is a plain, container-resolved settings object, configured the same
way regardless of which one it is:

```php
$app->configure(function (SomeOptions $opts) {
    $opts->someSetting = 'value';
});
```

An unconfigured Options class still resolves — every setting below already shows its default.

### [CompressionOptions](#gzip-compression)

| Setting | Default | Description |
|---|---|---|
| `enabled` | `true` | Master on/off switch for gzip response compression everywhere. |
| `minBytes` | `1024` | A response body shorter than this (bytes, before compression) is always sent uncompressed. |

### [CorsOptions](#cors)

| Setting | Default | Description |
|---|---|---|
| `allowOrigin` | `null` | `Access-Control-Allow-Origin` value. Unset omits the header entirely — CORS is off by default. |
| `allowMethods` | `null` | `Access-Control-Allow-Methods` value, e.g. `'GET, POST, OPTIONS'`. |
| `allowHeaders` | `null` | `Access-Control-Allow-Headers` value, e.g. `'Content-Type, Authorization'`. |
| `exposeHeaders` | `null` | `Access-Control-Expose-Headers` — response headers a cross-origin caller may read. |
| `maxAge` | `null` | How long (seconds) a browser may cache a preflight `OPTIONS` response. |
| `allowCredentials` | `false` | Sends `Access-Control-Allow-Credentials: true` when `true`; otherwise the header is omitted. |

### [CsrfOptions](#csrf-protection)

| Setting | Default | Description |
|---|---|---|
| `secret` | *(required — uninitialized)* | HMAC signing key for issued tokens. Unconfigured (the default), CSRF checking is off entirely — every request passes through unchecked. |
| `ttl` | `3600` | How long an issued token stays valid, in seconds; also the CSRF cookie's own Max-Age. |
| `cookieName` | `'csrf_token'` | Name of the double-submit cookie the token is issued under. Deliberately not HttpOnly — client-side JS must be able to read it. |
| `headerName` | `'X-CSRF-Token'` | Request header checked first for the submitted token (the AJAX/fetch path). |
| `fieldName` | `'_csrf'` | Request body field checked when the header isn't present (the classic no-JS `<form>` path). |
| `cookieSecure` | `true` | The CSRF cookie's own `Secure` attribute. Set `false` explicitly for a local HTTP-only dev environment. |
| `cookieSameSite` | `'Lax'` | The CSRF cookie's own `SameSite` attribute. |

### [EnvironmentOptions](#environment-variables)

| Setting | Default | Description |
|---|---|---|
| `localConfigFile` | `'config.local.json'` | Gitignored, per-developer override file, read last so it wins over everything else. `null` disables this layer. |

Configuration mainly happens through `load(?string $dir = null)`, not a plain property — see
[Environment Variables](#environment-variables) for the full `.env`/`config.json` layering it drives.

### [FileSystemOptions](#routedi-compilation-caching--production-performance)

| Setting | Default | Description |
|---|---|---|
| `cacheDirectory` | `null` (→ system temp dir) | Where the compiled route/DI/attribute cache and compiled view assets are written. See [Route/DI Compilation Caching](#routedi-compilation-caching--production-performance). |
| `publicDirectory` | `null` (disabled) | A plain directory served as static files ahead of routing. See [Static Files](#static-files). |
| `assetsPath` | `'/assets'` | URL path prefix compiled view/layout CSS/JS is served under, e.g. `GET {assetsPath}/{hash}.css`. See [Views](#views). |
| `cacheValidate` | `true` | Re-verify the cache is fresh (reflect + compare mtimes) on every request; `false` is "trust mode". |

### [JWTOptions](#jwt-authentication)

| Setting | Default | Description |
|---|---|---|
| `secret` | *(required — uninitialized)* | Signing secret. Accessing it unconfigured throws immediately rather than signing with a guessable default. |
| `alg` | `'HS256'` | Signing algorithm. |
| `ttl` | `3600` | Token lifetime, in seconds. |
| `tokenType` | `'Bearer'` | Expected prefix on the `Authorization` header value. |
| `header` | `'Authorization'` | Request header the token is read from. |
| `cookieName` | `null` (disabled) | Cookie name to additionally read the token from when the header didn't produce one — lets a signed-in session survive a plain page load, not just fetch()/XHR calls. |
| `loginRedirectUrl` | `null` (disabled) | Where an `UnauthorizedException` (e.g. `#[Authenticated]` with no/expired JWT) sends a page navigation — a real browser navigation (`Sec-Fetch-Mode: navigate`) or a `waypoint.js` partial one (`X-Waypoint-Accept: partial`) — as a `302` instead of `401` JSON. Any other `fetch()`/XHR call still gets the `401`. |

### [LoggerOptions](#logging)

| Setting | Default | Description |
|---|---|---|
| `levels` | `[]` (→ all levels) | PSR-3 levels this logger accepts, e.g. `['error', 'critical']`. |
| `name` | `null` | Optional channel/name for this logger. |

Loggers are registered through `add($logger, $options)` / `addMono($channel, $handler, $levels)`, not
plain properties — see [Logging](#logging).

### [OpenAPIOptions](#openapi)

| Setting | Default | Description |
|---|---|---|
| `enabled` | `false` | Serve `spec.json`/`spec.{version}.json`/`swagger.html` at all. |
| `path` | `'/openapi'` | URL prefix the endpoint lives under. |
| `title` | `'API Documentation'` | OpenAPI `info.title`. |
| `version` | `'1.0.0'` | OpenAPI `info.version`. |
| `description` | `'Generated API documentation'` | OpenAPI `info.description`. |
| `servers` | `[]` | List of server URL entries. |
| `tags` | `[]` | Top-level tag definitions. |
| `securitySchemes` | `[]` | `components.securitySchemes` entries — pair with `#[Throws]`/route-level auth as needed. |
| `security` | `[]` | Top-level `security` requirement entries. |
| `externalDocs` | `null` | Optional `{description, url}` external documentation link. |

### [RendererOptions](#views)

| Setting | Default | Description |
|---|---|---|
| `directory` | `null` | Where view/layout `.php` templates (and their sibling `.css`/`.js`) live. |
| `layout` | `'_Layout'` (no extension — matches nothing) | Default layout template; pass an explicit `.php` name, or every render falls back to bare view content. |

### [SecurityHeaderOptions](#security-headers)

| Setting | Default | Description |
|---|---|---|
| `contentTypeOptionsEnabled` / `contentTypeOptions` | `true` / `'nosniff'` | `X-Content-Type-Options`. |
| `frameOptionsEnabled` / `frameOptions` | `true` / `'DENY'` | `X-Frame-Options`; set `'SAMEORIGIN'` to allow same-origin framing. |
| `referrerPolicyEnabled` / `referrerPolicy` | `true` / `'strict-origin-when-cross-origin'` | `Referrer-Policy`. |
| `hstsEnabled` / `hsts` | `true` / `'max-age=31536000; includeSubDomains'` | `Strict-Transport-Security` — only ever sent on a request that itself arrived over HTTPS, regardless of this flag. |
| `cspEnabled` / `csp` | `false` / `null` | `Content-Security-Policy` — opt-in only; a wrong one-size-fits-all default would break real pages instead of just failing open. |

---

## Contributing

Contributions welcome! Please open issues or pull requests on GitHub.

Before opening a pull request:

```bash
composer test            # PHPUnit
composer stan            # PHPStan level 10
composer coverage:text   # 100% line coverage is the bar
```

The client (`waypoint.js`) lives in `ui/` (TypeScript, Vitest); `composer build:ui` writes the bundle
into `src/Waypoint/UI/waypoint.js` — commit that file with the change, CI fails when it is stale.
New features that go beyond the core belong in a plugin package (see [Plugins](#plugins)).

---

## License

MIT License

---

## Contact

For questions or support, open an issue or contact the maintainer.

---

*Happy coding with Waypoint!*
