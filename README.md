[![Latest Version](https://img.shields.io/packagist/v/systonia/waypoint.svg)](https://packagist.org/packages/systonia/waypoint)
![PHP Version](https://img.shields.io/badge/PHP-8.5-blue)
![Build](https://github.com/Systonia/Waypoint/actions/workflows/build.yaml/badge.svg)
![Dependency Audit](https://github.com/systonia/Waypoint/actions/workflows/audit.yaml/badge.svg)
![PHPStan Level 10](https://github.com/systonia/Waypoint/actions/workflows/phpstan.yaml/badge.svg)
[![PSR-3](https://img.shields.io/badge/PSR--3-compatible-brightgreen.svg)](https://www.php-fig.org/psr/psr-3/)
[![PSR-4](https://github.com/Systonia/Waypoint/actions/workflows/psr-4.yaml/badge.svg)](https://www.php-fig.org/psr/psr-4/)
![License](https://img.shields.io/badge/license-MIT%20OR%20Apache--2.0-blue)

# Waypoint

A lightweight PHP framework for building APIs and server-rendered UIs, with attribute-based routing.

**Documentation:** the [guides](docs/guides/index.md) explain each part of Waypoint with complete examples, and the [reference](docs/reference/index.md) lists every class and method. Start at [docs/index.md](docs/index.md).

---

## Overview

Waypoint is for a team that wants **one small framework covering both sides of a typical app**: a JSON
API and a server-rendered UI (plain pages and forms, no separate frontend build), with no config files, service definitions or implicit
magic. Routing, dependency injection, validation, and the OpenAPI spec are all driven directly off PHP
attributes on your own classes; there's nothing else to wire up or keep in sync.

It also ships production-grade defaults for the things a real API needs (RFC 9457 structured error
responses, CSRF protection, security headers, request correlation IDs, JWT auth), on by default or a
few lines of `configure()` away, rather than left to every app to reimplement. And it's built to be fast
under real load: attribute discovery compiles to a cache with an opt-in "trust mode" for production,
benchmarked well over an order of magnitude faster than uncached reflection-based discovery.

The core stays small on purpose. Everything beyond routing, DI, views, security and the OpenAPI
generator (translations, LDAP, sessions, rate limiting, exports) is a separate package that plugs
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
  CSS/JS per view, `Redirect` results, and the bundled `waypoint.js` client for CSRF headers and `wp-*` handlers
- Static file serving from a configured public directory
- Gzip response compression, size-thresholded, with an opt-out `#[NoGzip]` attribute per controller/route
- PSR-3 compatible logging (plug in any PSR-3 logger, or use `LoggerOptions::addMono()` for Monolog)
- CLI task runner (`#[Manager]`/`#[Task]`), for the same app to serve HTTP and run background jobs
- A complete OpenAPI 3.1.0 generator (`OpenAPIOptions::$enabled`), driven by a compiled attribute cache
  rather than live reflection
- Optional route/DI compilation caching with an opt-in "trust mode" for production deploys
- A plugin system with fixed hooks (endpoints, route attributes, guards, argument binders, renderers,
  OpenAPI extensions, view helpers, client assets), so features ship as packages instead of growing the core
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
- [Types and PHPStan](#types-and-phpstan)
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

$app->serve();
```

`attach()` discovers every class reachable from your controllers via `#[Inject]` (see
[Dependency Injection](#dependency-injection) below) and wires up the router and container. Outside
Tenma, `serve()` branches on `php_sapi_name()`: under a real
HTTP SAPI it dispatches the current request (`handleHttp()`); under `cli` it runs the CLI task runner
against the process's own `$argv` (`runCli()`, see [CLI Tasks](#cli-tasks)).

A runtime that hands requests over itself, instead of filling the superglobals, builds the request with
`Request::fromParts()` and calls `handle()`. It returns the finished `Response` (gzip, `HEAD` and
`Content-Length` already applied) without sending anything:

```php
$res = $app->handle(Request::fromParts('GET', '/customers/7?tab=orders', $headers, $body, $cookies, https: true, ip: $peer));
// $res->getStatus(), $res->getHeaders(), $res->getCookies() (one Set-Cookie value each), $res->getBody()
```

`$req->ip` is the address the connection came from (`REMOTE_ADDR` under FPM): the client's, or a proxy's in front
of it. Behind a reverse proxy, name it in `ProxyOptions`; a request from it then carries the client's address
from `X-Forwarded-For` and the scheme from `X-Forwarded-Proto` (`$req->https`). From anyone else both headers
are ignored:

```php
$app->configure(function (ProxyOptions $proxies) {
    $proxies->trusted = ['127.0.0.1', '::1', '10.0.0.0/8'];
});
```

An entry that is no IP address or CIDR range (`'10.0.0.0/'`, `'localhost'`) throws an `InvalidArgumentException`
right there. Assign the whole list; appending with `[]` is not possible.

Under Tenma (PHP with an event loop) the app itself is the request handler:
one process serves every request, each in a Fiber of its own, interleaved while they wait on I/O. The
request id and the CSRF token follow a request into the tasks it starts (`Tenma::async()`); files under the
`static` prefixes are served by Tenma itself, without PHP:

```php
$app->configure(function (FileSystemOptions $fs) {
    $fs->publicDirectory = __DIR__ . '/public';
});
$app->attach([...]);
$app->serve();
```

`serve()` is the one line for every runtime: under Tenma it starts the server on `PORT` from the Environment
(8080 without one, or `serve(3000)`) and hands `FileSystemOptions::$publicDirectory` to Tenma, which serves it
without PHP (ETag, ranges); under FPM, `php -S` or the CLI it is `run()`, and Waypoint serves the directory.

A controller may then wait on Tenma's async I/O (`Tenma\Db`, `Tenma::fetch()`, `Tenma::all()`) while the
process serves other requests. Read [Controllers per request, services shared](#controllers-per-request-services-shared)
first. In a template, don't wait: fetch the data in the controller before `render()`.

A route can hand the request over to Tenma with `Response::upgrade()`: a WebSocket
(`Tenma\Http\Response::websocket()`), a stream or server-sent events (`stream()`, `events()`). The route's
guards, `#[Authenticated]` and middlewares run first as for any other route; Tenma's response then replaces
Waypoint's. A stream keeps the headers and cookies set on `$res` (CORS, security headers, the request id), its
own headers and status win; a WebSocket answers with its handshake only. If an exception handler writes
the response instead (the controller or an `after()` threw after the handover), the error goes out and the
upgrade is dropped. The handler runs after the request ended, but with the request's context restored: the
request id (on every log line), the CSRF token and whatever a plugin keeps in a `FiberLocal`. Values from the
request itself, such as the JWT claims, go in through `use`:

```php
#[Get('/chat')]
#[Authenticated]
public function chat(Request $req, Response $res): void
{
    $userId = $req->jwt['sub'];
    $res->upgrade(\Tenma\Http\Response::websocket(function (\Tenma\Http\WebSocket $socket) use ($userId) {
        while (($message = $socket->receive()) !== null) {
            $socket->send("$userId: $message");
        }
    }));
}
```

WebSockets don't carry the CSRF header, so a WebSocket route that acts for a logged-in user checks the
`Origin` header itself.

For any server that speaks PSR-7, `Waypoint\Psr\Handler` makes the app a PSR-15 request handler: Tenma
through `tenma/psr`, any other PSR-7 server, or behind PSR-15 middlewares of other packages. It takes any
PSR-17 factory:

```php
$factory = new \Nyholm\Psr7\Factory\Psr17Factory();
$handler = new \Waypoint\Psr\Handler($app, $factory);

Tenma::serve(8080, \Tenma\Psr\Psr::handler($handler, $factory));   // or hand $handler to your server
```

Each request is translated in and out, so the direct `Tenma::serve(8080, $app)` stays the faster way under
Tenma. PSR-15 middlewares run around the whole app, not per route, and see neither guards nor
`#[Authenticated]`. `Response::upgrade()` works through the handler only under Tenma with `tenma/psr`.

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

A route method declares its path with `#[Get]`/`#[Post]`/`#[Put]`/`#[Patch]`/`#[Delete]`; that
attribute *is* the route (there is no separate `#[Route]` attribute). `#[Controller('/prefix')]`
optionally prefixes every route in the class. A `HEAD` request runs the `GET` route and sends its
headers without the body. A path that exists under other methods answers `405` with an `Allow`
header instead of `404`. Parameters are then bound with one of three attributes
depending on where the value comes from:

| Attribute   | Description                                                     | Example                              |
|-------------|-------------------------------------------------------------------|---------------------------------------|
| `#[Param]`  | Binds a route placeholder (e.g. `{customerId}`)                  | `#[Param] string $customerId`         |
| `#[Query]`  | Binds a query string (`?foo=`) parameter                         | `#[Query] string $search`             |
| `#[Body]`   | Binds the request body to a DTO: JSON for a JSON `Content-Type` (`application/json`, `application/*+json`), form fields otherwise | `#[Body] UserDTO $user`               |

An unattributed scalar-typed parameter (`string`/`int`/`float`/`bool`) is bound implicitly: Waypoint
tries the route placeholder first, then falls back to the query string. The text converts by its
meaning: `bool` accepts `true/false`, `1/0`, `yes/no` and `on/off` (so `?active=false` is `false`),
`int` accepts only whole numbers, `float` only numbers. Anything else, like `?page=abc`, answers `422` with the
parameter in `errors` instead of silently becoming `0`. A backed enum parameter (`#[Query] OrderStatus
$status`, or unattributed) gets the case its value names via `tryFrom()`, and `422` listing the allowed values
otherwise. `#[Body] array $data` gets the decoded body as it came. `Request`/`Response`
type-hinted parameters (or properties) are injected directly, no attribute needed. A non-nullable parameter
none of this binds stops the app at boot with an `InvalidArgumentException` instead of failing each request.

Placeholder values arrive URL-decoded, once: `/user/J%C3%BCrgen` binds `Jürgen`, `/user/%2541` binds `%41`,
and an encoded `%2F` stays inside its own segment. Everything around the placeholders matches literally, so
`/file/{name}.json` matches `/file/report.json` but not `/file/reportxjson`.

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

Class-level attributes are inherited from base controllers, although PHP itself doesn't inherit
attributes: an `#[Authenticated]`, `#[Role]`, `#[Middleware]` or `#[Version]` on an abstract base
controller applies to every subclass route. The nearest declaration wins, and a base controller's
middlewares run outside the subclass's own. `#[Controller]` itself is never inherited.

### Array/collection request bodies

`#[Body]` normally requires the parameter to be typed as a DTO class. For an endpoint whose body is a
JSON array of objects, type the parameter `array` and give `#[Body]` an `of:` element class instead.
Each element is hydrated into (and validated against) that DTO individually:

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

The body must be a JSON list of objects. A single object answers `422` with a `body` error, and an
element that is no object (`[1, "two"]`) answers `422` with an error under its index.

This is reflected in the generated OpenAPI schema too: the request body documents as
`{"type": "array", "items": {"$ref": "#/components/schemas/LineItemDTO"}}` rather than a single `$ref`.

### File uploads

A `multipart/form-data` request carries its fields in `$req->body` and its files in `$req->files`, nested as
the field names say (`photos[]` becomes a list). `$req->file()` returns one, named as in the form: `'avatar'`,
`'doc[a]'`, `'photos[1]'`. An empty file input is left out, and a list stays numbered without gaps. The same works
under FPM, Tenma, any PSR-7 server and the testing kit (`->withFile('avatar', 'me.png', $bytes, 'image/png')`):

```php
#[Post('/profile/avatar')]
public function avatar(Request $req): Redirect
{
    $avatar = $req->file('avatar') ?? throw new ValidationException(errors: ['avatar' => 'Choose a picture.']);
    if (!$avatar->isValid() || getimagesizefromstring($avatar->contents()) === false) {
        throw new ValidationException(errors: ['avatar' => 'This is not a picture.']);
    }
    $avatar->moveTo(__DIR__ . '/../storage/avatars/' . bin2hex(random_bytes(8)) . '.png');
    return new Redirect('/profile', 303);
}
```

`name`, `type` and `size` come from the client: never use the name as a path, and check the content before
trusting the type. `isValid()` is false when PHP or the runtime refused the file (`$error` holds the
`UPLOAD_ERR_*` code). `moveTo()` works once; under Tenma it writes without blocking the worker. The size limit is
`upload_max_filesize`/`post_max_size` under FPM and `--max-body` under Tenma.

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

`#[OneOf]` compares strictly (`'1'` is not `1`) and, like `#[Email]`, `#[Regex]` and `#[Length]`, skips `null`; `#[SameAs]` compares
against the named property of the same DTO.

A failing DTO throws `ValidationException`, which the default exception handler turns into a `422`
RFC 9457 Problem Details response, with per-field errors under `errors`:
`{"type": "about:blank", "title": "Validation failed", "status": 422, "detail": "Validation failed", "errors": {"sku": "..."}}`.
See [Exception Handling](#exception-handling). The messages are English by default; a plugin can bind
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

Unknown keys in the input array are silently ignored, and so are keys naming a private, protected,
`protected(set)`/`private(set)` or static property: those are the DTO's own state, never request input. A writable
property without a default (`public string $name;`) is required: left unset, it fails `This value is required.`
(a `422`, not an uninitialized-property `500`). A JSON body that doesn't parse answers `400 Bad Request` before
any DTO is built. A backed enum property (`public OrderStatus $status`) gets the case its value names via `tryFrom()`, and
`This value should be one of: open, done.` otherwise. A property typed with another `FromArray` DTO is built
from the nested array, and an `array` property with `#[ListOf(LineItem::class)]` (`map: true` for string
keys) gets each element built as a DTO or enum case; nested errors are reported under
dotted keys (`delivery.city`, `items.0.sku`). A property with `#[Ignore]` is never set from input nor
required, and it is left out of JSON/XML results and the OpenAPI schema too. A property that must only never
be settable this way (e.g. a server-computed value) can be excluded by overriding `fromArrayExcludes()`:

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
`__construct()` (which overrides the trait's, same as any other method) and call
`$this->hydrateFromArray($data)` from it directly.

#### Validating by hand (`$dto->isValid`, `$dto->errors`, `fail()`)

For an HTML form handler that re-renders the page with the errors, bind with `#[Body(validate: false)]`: the
method gets the DTO even when it is invalid, and a `View` it returns then answers with status `422`. Built by
hand or bound that way, the DTO validates itself. `isValid` and `errors` are computed properties: every read applies the attribute
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
`#[Inject]`-typed properties/parameters starting from your registered controllers. Any class reachable
that way is auto-instantiated and shared as a singleton. **There is no separate "mark this class as a
service" attribute**: reachability via `#[Inject]` is what registers a class.

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
- `Request`, `Response` and `Router` can be injected into controllers and `#[Middleware]` classes; they are
  set per request, never taken from the container.
- Container-managed classes are constructed with no arguments and populated purely through
  `#[Inject]`-marked properties. There is currently no constructor-argument resolution; a
  constructor parameter marked `#[Inject]` is *discovered* (so its type is pulled into the container
  graph) but not itself resolved/passed as a constructor argument. Prefer property injection.

PHPStan would report an `#[Inject]` property as only read, since the container writes it: the package's
`extension.neon` counts such properties as written and initialized (loaded by phpstan/extension-installer, or
add `vendor/systonia/waypoint/extension.neon` to `includes`). It also reads `#[Returns]` and `#[ListOf]` as types
(see [Types and PHPStan](#types-and-phpstan)).

Outside controllers and services (the front controller, a script), `$app->get(Service::class)` returns the
container's instance and throws `ServiceNotFoundException` when it can't be built; `$app->find(Service::class)`
returns `null` instead.

### Controllers per request, services shared

Controllers and `#[Middleware]` classes are created fresh for every request, so an `#[Inject] Request`
or `#[Inject] Response` on them always belongs to the request being handled. A controller you registered
in the container yourself (one with constructor arguments) is cloned per request, keeping what its
constructor set up. Under FPM nothing changes: there a controller never outlived its request anyway.

Services are the opposite: one instance per process, shared by every request. On a long-lived worker
(one process that serves request after request, such as Tenma), and on one that runs requests interleaved while they wait
on I/O, anything a service keeps in a property is visible to every other request. So **a service must
not keep per-request state in its properties**:

```php
class AuthService
{
    private ?User $user = null;

    public function signIn(Request $req): void { $this->user = User::fromJwt($req->jwt); } // wrong
    public function user(): ?User { return $this->user; }                                  // another request's user
}
```

The same goes for memoizing per request (`$this->current ??= ...` survives into the next request),
switching shared state per request (`$translator->setLocale(...)`, `$db->setTenant(...)`), collecting per
request (a list of messages, a query counter) and half-built objects (set fields, wait, then `build()`).

Fine in a service: configuration and dependencies that never change after boot, caches whose content
holds for everyone (compiled templates, exchange rates; nothing user-specific unless the user is part of
the key), and stateless helpers that get everything they need as arguments.

Instead of per-request state in a service:

- pass it along as an argument: `$auth->user($req)` rather than `$auth->user()`;
- keep it on the request, as `useJwt()` does with `$req->jwt`;
- or, for a value that really has to be implicitly "current" (a request id for log lines), hold it in a
  `Waypoint\Support\FiberLocal`, as `RequestContext` and `Csrf` do.

Waypoint can't enforce this rule; under FPM breaking it goes unnoticed, since every request starts with a
fresh process.

---

## Middleware

Global middleware runs on every request, registered via `use()` with an instance of a class that extends
`Waypoint\Http\MiddlewareBase` (see below for `before()`/`after()`):

```php
use Waypoint\Attributes\Inject;
use Waypoint\Environment;
use Waypoint\Http\{MiddlewareBase, Request, Response};

final class MaintenanceMode extends MiddlewareBase
{
    #[Inject]
    private Environment $env;

    protected function before(Request $req, Response $res): bool
    {
        if ($this->env->get('MAINTENANCE') !== 'on') {
            return true;
        }
        $res->status(503)
            ->withHeader('Retry-After', '600')
            ->withHeader('Content-Type', 'text/plain')
            ->write("Back in a few minutes.\n");
        return false;
    }
}

$app->use(new MaintenanceMode());
```

The instance is shared by all requests, so a middleware passed to `use()` keeps nothing per request in its
properties.

`useCors()` and `useJwt()` (see below) are both just built-in middleware registered this way.

`$req->path` is the path the router matches: slashes at either end trimmed and percent-decoded, so `//admin/`
and `/%61dmin` are both `/admin`, and a check on it covers every spelling that reaches the same route. A path
with an encoded slash (`%2F`) or NUL in a segment stays encoded as a whole.

Per-route middleware is declared with `#[Middleware(Class::class)]` directly on a controller method
(repeatable). **The class must extend `Waypoint\Http\MiddlewareBase`**: its `handle()` is what runs, and a
`#[Middleware(...)]` naming a class that doesn't exist or doesn't extend it fails the route compile with an
`InvalidArgumentException`, since a skipped middleware would leave the route running without its check. `MiddlewareBase::handle()` is `final` and always runs `before()` → `$next()` →
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
chain short-circuits there, typically because `before()` already threw, or built its own response.
`before()`/`after()` are both no-ops by default, so a subclass only overrides whichever one it needs. The
class is resolved through the container at dispatch time (so it can itself use `#[Inject]`) rather than
being instantiated directly.

Because `MiddlewareBase` also implements `__invoke()` (delegating to `handle()`), the same subclass works
on routes and on the `$app->use()` pipe alike:

```php
$app->use(new SecurityHeadersMiddleware()); // see below
```

### Security Headers

`SecurityHeadersMiddleware` fills in the standard hardening response headers (`X-Content-Type-Options`,
`X-Frame-Options`, `Referrer-Policy`, and on HTTPS requests only `Strict-Transport-Security`), enabled by
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

The headers are set on the way in, so an error response from a later middleware or the controller carries
them too. A controller, or a middleware after this one, that sets one of these headers replaces it; one
that a middleware before this one already set is kept.

### CSRF Protection

Stateless, double-submit-cookie CSRF protection for state-changing requests, with no server-side session
store: a token is a self-contained, HMAC-signed `{exp, nonce}` pair, verified against
`CsrfOptions::$secret`. Configuring a secret is what opts an app into it at all. Unconfigured (the
default), every request passes through unchecked, so this never breaks an app that doesn't use it:

```php
use Waypoint\Environment;
use Waypoint\Options\CsrfOptions;

$app->configure(function (CsrfOptions $opts, Environment $env) {
    $opts->secret = $env->get('CSRF_SECRET'); // required to actually enable checking, at least 32 bytes
    $opts->cookieSecure = null;               // default: Secure exactly when the request came over HTTPS
});
```

Once a secret is set, every `POST`/`PUT`/`PATCH`/`DELETE` request is checked automatically (`GET`/`HEAD`
never carry a body/side effect worth protecting). There is no middleware to register. A token is issued as a
cookie (`CsrfOptions::$cookieName`, default `csrf_token`) on every rendered `View` (see
[Views](#views)), and must be repeated back by the client either as a request header
(`CsrfOptions::$headerName`, default `X-CSRF-Token`, the AJAX/`fetch()` path) or a body field
(`CsrfOptions::$fieldName`, default `_csrf`, for a classic no-JS `<form>`). A mismatch, missing token, or
expired token throws `ForbiddenException` (403).

With `JWTOptions::$cookieName` set, each token is signed for the session cookie it was issued with. A
token someone fetched for their own session and planted in another browser (from a sibling subdomain,
say) is rejected there. Signing in or out changes the session, so the next rendered view issues a new
token.

Inside a view template, `$this->csrf` renders the classic no-JS `<form>` half of that pattern:

```html
<form method="post">
    {{ $this->csrf->field() }}  <!-- hidden input -->
</form>
```

The AJAX/`fetch()` half needs no app code at all if you're loading
[`waypoint.js`](#views) (see `$this->waypointJsTag()` in [Views](#views)): it patches `fetch()`
itself so every same-origin `POST`/`PUT`/`PATCH`/`DELETE` call automatically carries the header, reading
the token straight from the cookie. A plain `fetch()` call needs to know nothing about CSRF:

```js
fetch('/orders', { method: 'POST', body: JSON.stringify(data) }); // X-CSRF-Token attached automatically
```

Not using `waypoint.js`? Read the cookie and attach the header yourself the same way it does.
`Waypoint.csrf.token()` is also there directly, for a non-`fetch()` use (a WebSocket handshake, a
manually-built `XMLHttpRequest`). If `CsrfOptions` itself was reconfigured away from its default cookie/
header names, `$this->waypointJsTag()` passes the new names to `waypoint.js` automatically as
`data-csrf-cookie`/`data-csrf-header` on the `<script>` tag, with nothing to do in the template.

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

Configure `CorsOptions`, then enable the feature; `useCors()` takes no parameters:

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

Leaving a property unset omits its header entirely, so CORS is off by default. `allowCredentials` is a
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
five standard ones. `ValidationException` does exactly this, adding `errors` (the per-field/per-index
messages):

| Exception                | Status | Extra body member |
|---------------------------|--------|--------------------|
| `ValidationException`     | 422    | `errors` (per-field/per-index messages) |
| `ForbiddenException`      | 403    | none |
| `UnauthorizedException`   | 401    | none |
| `NotFoundException`       | 404    | none |
| Any other `Throwable`     | 500    | none (message never exposed, not even in development; see below) |

A non-`HttpException` `Throwable` (anything unexpected/unhandled) maps to a generic 500 Problem Details
body with a fixed `"Internal Server Error"` title, **never** `$e->getMessage()` or a stack trace, in any
environment: an unexpected exception's message can easily contain internal details (a file path, a query,
a raw driver error) never meant to reach whoever triggered the crash, so `Environment::isDev()` alone
isn't treated as a safe enough gate for that. It's still logged in full via the container's `Logger`. A
project that wants more than the generic message needs its own explicit
`useExceptionHandler(Throwable::class, ...)` override: an actual opt-in, not an implicit one.

Register your own handler with `useExceptionHandler()`, for an exact exception class or anything further
up its class/interface hierarchy. The most specific registered handler wins: exact class match
first, then parent classes, then implemented interfaces, then the `Throwable` catch-all. The handlers
cover app-level middleware (`$app->use()`) as well as routes, so an auth check there can simply throw.

```php
use Waypoint\Http\{Request, Response};

$app->useExceptionHandler(MyDomainException::class, function (MyDomainException $e, Request $req, Response $res) {
    $res->status(409)
        ->withHeader('Content-Type', 'application/json')
        ->write(json_encode(['error' => $e->getMessage()]));
    // no need to call ->send(), see below
});
```

Calling `useExceptionHandler()` again for the same class overrides the previous handler, including
the built-in `HttpException`/`Throwable` defaults above.

A handler only needs to build `$res` (status/headers/body) and return: `App::handleHttp()` sends the
response exactly once, itself, after the whole middleware pipe has unwound. `Response::send()` stays
harmless for compatibility (inside `App::handle()` it does nothing, the caller sends the finished
response), but it's no longer the recommended shape for a new handler.

---

## Views

Return `Waypoint\Http\View` from a route method instead of an array to render server-side HTML, with
layouts, named sections, and per-view/per-layout scoped CSS/JS:

```php
use Waypoint\Http\View;

#[Get('/products/{id}')]
public function show(#[Param] string $id): View
{
    $product = Product::find($id);
    return new View('ProductDetail', $product);
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

`$opts->layout` defaults to `'_Layout'` with **no extension**, which never matches a real file. Pass an
explicit `.php` name, or no layout is applied and every render falls back to the bare view content.

Inside a view/layout `.php` file, `$model` is whatever was passed to `View`'s constructor (commonly an
array, e.g. `$model['product']`), and `$this` is the `View` instance itself.

A view is PHP with a few directives on top:

```html
@use (App\Status)
@model (App\Models\ProductPage)
@section('title'){{ $model->product->name }}@endsection

<h1>{{ $model->product->name }}</h1>
@foreach ($model->product->tags as $tag)
    <span @class.new="$tag->isNew()">{{ $tag->label }}</span>
@endforeach
@if ($model->status === Status::SoldOut)
    <p>Sold out</p>
@endif
```

and its layout places the sections and the page: `<title>@yield('title')</title>`, `<main>{{ $content }}</main>`.

`{{ $value }}` and `<?= $value ?>` are HTML-escaped. Markup goes out as it is only as a `Waypoint\Html` value
(`{{ new Html($trusted) }}`) or through `<?php echo $markup ?>`; the layout's `$content`, `@yield`, `section()`,
`assetTags()`, `scopeAttribute()`, `waypointJsTag()`, `pluginAssetTags()` and `$this->csrf->field()` return
`Html` already. Printing an array throws a `TypeError`. `@if`/`@elseif`/`@else`/`@endif`, `@foreach`,
`@code ... @endcode`, `@use (Class)`, `@class.name="$cond"`, `{{/* comments */}}` and `@@` work as in Live
templates; plain PHP keeps working. `@model (Type)` names the type of `$model` for PHPStan and the editor, and
in development a model of another type is a `TypeError`. A mistake in the syntax keeps the view from rendering,
with its line, and an error while it renders keeps the view's file and line (`View::origin()`, the error log).
With a cache directory the compiled templates are files under `{cacheDirectory}/views/`, so OPcache keeps them.

`vendor/bin/waypoint-check` checks every view without running the app: the compiler's mistakes, and PHPStan's
findings with `$this` as the `View` and `$model` of its `@model` type, at the view's lines. Under `tenma lsp`
the editor knows the views too (completion of `$this->` and `$model->` and their chains, hover, F12, the
mistakes while typing). The variable of a loop over a `#[ListOf]` property or a `#[Returns(list: true)]` method
gets the element class there, in views, Live templates and plain PHP files alike. See
[Views](docs/guides/views.md) for the full syntax.


- `$this->request` (read-only), `$this->env` and `$this->csrf`: the request being answered, the
  `Environment` and the CSRF helper.
- `@section('name')` ... `@endsection` / `@yield('name')`, the same as `$this->startSection('name')` /
  `$this->endSection()` / `$this->section('name', required: false)`: named content blocks a layout pulls in,
  e.g. for per-page `<head>` scripts.
- `$this->assetTags()` / `$this->layoutAssetTags()`: `<link>`/`<script>` tags for the current view's own
  sibling `.css`/`.js` (`ProductDetail.css` next to `ProductDetail.php`), and for the layout's own, if
  either has one.
- `$this->scopeAttribute()` / `$this->layoutScopeAttribute()`: a `data-view="..."` attribute for the
  element that content should be scoped to; the matching CSS is automatically wrapped in a
  `[data-view="..."]` nesting selector, so plain rules in `ProductDetail.css` only ever apply where that
  attribute is present.

The client half, `waypoint.js` (automatic CSRF headers, `wp-onclick`/`wp-onsubmit` handlers and
directives for plugin scripts), ships inside the package and is compiled into the same asset pipeline as view CSS/JS: served at
`{assetsPath}/waypoint.{hash}.js` with immutable caching, rebuilt when the framework is upgraded. The
layout emits the tag with `{{ $this->waypointJsTag() }}`; when `CsrfOptions` was changed from its
defaults the tag also carries `data-csrf-cookie`/`data-csrf-header` for the client, so nothing is
duplicated in the template.

A view or layout's sibling `.css`/`.js` is discovered automatically (same basename, same directory,
either role), with nothing to register. Views can live in subdirectories: `views/Admin/Users.php` is
`new View('Admin/Users')`, always `/`-separated, and its `Admin/Users.css` is found, scoped
(`[data-view="Admin/Users"]`) under that same name. A name that starts with `/` or
contains a `..` segment is rejected at construction, so a view name built from request input can never
reach outside the views directory. The layout is still resolved from the root of the directory only.
Compiled, content-hashed copies are written under
`{cacheDirectory}/assets/` (kept in memory without a cache directory; see [Route/DI Compilation Caching](#routedi-compilation-caching--production-performance)
below) and served through `GET /assets/{hash}.css`/`.js` with a year-long, immutable `Cache-Control`
header.

Passing `partial: true` skips the layout entirely and renders just the view, for a bare HTML fragment. `directory:` renders view and layout from another directory, such as a plugin's own views; such a view gets no compiled CSS/JS of its own.
Navigation between pages is plain browser navigation: links and forms load whole pages. For smooth
transitions between them, CSS covers it without JavaScript: `@view-transition { navigation: auto; }`.

### Redirects

Return `Waypoint\Http\Redirect` to send a redirect instead of a body, typically from a form `POST`
that succeeded (POST/redirect/GET), next to the `View` the same handler renders when it didn't:

```php
use Waypoint\Http\{Redirect, Request, Response, View};

#[Post('/login')]
public function loginSubmit(Request $req, Response $res): View|Redirect
{
    if (!$this->credentialsValid($req)) {
        return new View('Login', ['error' => 'Invalid email or password.']);
    }
    JWT::signIn($res, ['sub' => $userId]);
    return new Redirect('/dashboard');
}
```

`new Redirect($location, $status = 302)` accepts any `3xx` (e.g. `303` for an explicit See Other, `301`/`308`
for a permanent move, `307` to preserve the original method). The response is just the status and a
`Location` header (no body, and the route's formatter is ignored), while anything the handler already
queued on the `Response` (cookies, headers) is kept. A `$location` that is empty or contains a line break
is rejected at construction. A redirect is an ordinary result, not an exception: the cases that *should*
redirect on failure (no/expired JWT on an `#[Authenticated]` route) already go through
`JWTOptions::$loginRedirectUrl` (see [JWTOptions](#jwtoptions)).

---

## Static Files

Serve a plain public directory (images, fonts, a pre-built frontend bundle, etc.) by configuring
`FileSystemOptions::$publicDirectory`. It is checked before routing on `GET` and `HEAD`, so a matching file
always wins over a route at the same path; any other method goes to routing:

```php
use Waypoint\Options\FileSystemOptions;

$app->configure(function (FileSystemOptions $fs) {
    $fs->publicDirectory = __DIR__ . '/../public';
});
```

Left unset (the default), no static directory is served at all. Only files inside that directory are
served: a path with a `..` or `.` segment, a dotfile or dot-directory (`.env`, `.git/`) and PHP source
(`.php`, `.phtml`, `.phar`, …) always 404, and the resolved file (symlinks followed) must lie inside the
directory itself (a sibling such as `public-backup` doesn't count). So the front controller can sit in
the same directory without its source ever being handed out. `.css`/`.js`/`.mjs`/`.json`/`.svg`/`.html` get a fixed
`Content-Type`; anything else falls back to `mime_content_type()`, then `application/octet-stream`. Every file
carries `Last-Modified`, and `If-Modified-Since` with that date or later answers `304 Not Modified`.

---

## Gzip Compression

Every response funnels through `Response::send()`, which gzips the body when the client's
`Accept-Encoding` header allows `gzip` (named or by `*`, with a quality above 0, so `gzip;q=0` refuses it) and the body meets a configurable size threshold. Compressing
a tiny response is a net loss once gzip's own framing overhead is counted, so anything shorter than
`CompressionOptions::$minBytes` (default `1024` bytes) is always sent uncompressed. On by default, no
setup required:

```php
use Waypoint\Options\CompressionOptions;

$app->configure(function (CompressionOptions $opts) {
    $opts->minBytes = 2048; // default: 1024
    $opts->enabled = false; // default: true; false turns compression off everywhere
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

### XML Responses

`#[SimpleXmlFormatter]` sends the return value as XML under `<root>`. Nested arrays and objects become
nested elements, text is escaped, and a key that is no valid element name (a list index, say) becomes
`<item key="…">`. Properties with `#[Ignore]` are left out here as in JSON.

### File Responses

`#[FileFormatter]` sends the return value as a raw body instead of JSON. Return an `SplFileInfo` to send
a file from disk. A returned string is always the content itself, even when it happens to name a file,
so text from a user can never turn into a file read:

```php
use Waypoint\Attributes\{Get, FileFormatter};

#[Get('/invoice')]
#[FileFormatter(mimetype: 'application/pdf', download: true)] // filename defaults to the file's own name
public function invoice(): \SplFileInfo
{
    return new \SplFileInfo('/srv/invoices/2026-10.pdf');
}
```

---

## JWT Authentication

Configure `JWTOptions` before calling `useJwt()`. **The signing secret has no default**: it's an
uninitialized typed property, so using it unconfigured throws immediately rather than silently signing
tokens with a guessable value. Tokens are always HS256, and a token is only valid with a numeric `exp`
in the future (`JWT::encode()` always sets one):

```php
use Waypoint\Options\JWTOptions;

$app->configure(function (JWTOptions $opts, Environment $env) {
    $opts->secret = $env->get('JWT_SECRET'); // required, at least 32 bytes: bin2hex(random_bytes(32))
    $opts->ttl = 3600;                   // default, seconds
    $opts->tokenType = 'Bearer';         // default
    $opts->header = 'Authorization';     // default
    $opts->cookieName = null;            // default (disabled), see below
});

$app->useJwt();
```

Once enabled, a valid token's decoded payload is available as `$req->jwt` (an array, or `null`) in any
controller/middleware that receives the `Request`. `#[Claims] UserClaims $user` binds it to a `FromArray` DTO
instead; it implies `#[Authenticated]` (401 without a token, or with claims that don't fill the DTO), and a
nullable `?UserClaims` makes the user optional.

A plain browser page navigation never sends a bearer `Authorization` header; only an explicit
`fetch()`/XHR call that sets one itself does. Set `JWTOptions::$cookieName` to also read the token back
out of a same-named cookie whenever the header didn't already produce one, so a signed-in session
(e.g. one your login endpoint issued via `Set-Cookie: <name>=<token>; HttpOnly`) survives a full page
load too:

```php
$app->configure(function (JWTOptions $opts, CsrfOptions $csrf, Environment $env) {
    $opts->secret = $env->get('JWT_SECRET');
    $opts->cookieName = 'auth_token';
    $csrf->secret = $env->get('CSRF_SECRET');
});
```

A browser attaches that cookie to cross-site requests too, so signing in by cookie needs CSRF
protection: with `cookieName` set and no `CsrfOptions::$secret`, `useJwt()` fails every request with a
500 instead of running unprotected.

Issue that cookie from your own login endpoint with `JWT::signIn($res, $claims)`: it signs the claims and
sets the `cookieName` cookie (HttpOnly, `SameSite=Lax`, `Max-Age` of `ttl`, `Secure` when the request came over
HTTPS). `JWT::signOut($res)` removes it on logout:

```php
#[Post('/login')]
public function login(#[Body] LoginDTO $data, Response $res): array
{
    $token = JWT::signIn($res, ['sub' => $userId]);
    return ['accessToken' => $token];        // also usable as a Bearer token directly
}

#[Post('/logout')]
public function logout(Response $res): array
{
    JWT::signOut($res);
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

- `GET /openapi/spec.json`: the generated OpenAPI 3.1.0 document
- `GET /openapi/spec.{version}.json`: one `#[Version]`'s document (404 for an unknown version)
- `GET /openapi/swagger.html`: bundled Swagger UI

The generator is driven entirely by a compiled attribute cache (see
[Route/DI Compilation Caching](#routedi-compilation-caching--production-performance)) rather than live
reflection on every request, so serving the spec is cheap even under trust mode. It documents:

- Path/query parameters with their real PHP types (`#[Param]`/`#[Query]`, and unattributed scalars)
- `200` responses from the return type (a DTO's public properties, an enum's values) or from `#[Returns]`
  (type, list or map, description, status);
  routes returning a `View` or `Redirect` are left out of the document
- `#[Body]` DTO request bodies, including nested and self-/mutually-referencing (cyclic) models
- Backed enums as `{type, enum}`, nullable types as `type: [x, "null"]`, `#[ListOf]` arrays
  as `items`/`additionalProperties`, `required` for properties with neither a default nor `null`, and
  `readOnly` for properties a request cannot set
- `#[Body(of: ...)]` array/collection request bodies (see [above](#arraycollection-request-bodies))
- `#[Summary]`, `#[Tags]`, `#[Throws]`, `#[Deprecated]`, `#[Ignore]` (on a route or a DTO property)
- `#[Schema(name: ...)]` to override a model's generated component name; two DTOs with the same short name
  never share a schema (the second is named by its full class name with dots)
- `operationId` as `ShortClass_method`, the full class name only where two controllers share a short name
- `#[Throws(NotFoundException::class)]` without a status documents the exception's own (404 here)
- `#[Property(description:, format:, example:, deprecated:)]` to annotate individual model properties
- Security schemes configured via `OpenAPIOptions::$securitySchemes`/`$security`; routes with `#[Authenticated]`,
  `#[Role]` or `#[Permissions]` require them per operation (a JWT `bearerAuth` scheme when none is configured),
  public ones opt out of a document-wide `security` with `security: []`
- Automatic `401`/`403` responses for guarded routes and `422` for `#[Body]` DTOs and converted parameters

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
fallback header name; an upstream proxy's own, possibly non-UUID, id is exactly as valid a correlation id
as one Waypoint would generate itself), otherwise a fresh UUID v4 (`Waypoint\Support\Uuid::v4()`, public for
the app's own ids too). An incoming id is only taken when it
has at most 128 characters from `A-Z a-z 0-9 . _ : -`; anything else gets a fresh UUID, so a client can't
write into the logs. No configuration or middleware required.

The resolved id is mirrored back to the client as an `X-Request-ID` response header, and automatically
stamped onto every `Logger` call made during that request, under the `request_id` context key, so there is no need
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
whatever `LoggerOptions` currently holds. Inject it with `#[Inject]` into a controller/service.
As long as no logger is registered, errors and worse go to PHP's `error_log` (stderr under Tenma and
`php -S`, the server's error log under FPM), so a failure never vanishes unseen; `add(new NullLogger())` silences it:

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
`$app->serve()` (or `$app->runCli()`, which returns an exit code instead of calling `exit()`) reads the
process's own `$argv` and dispatches to a `#[Task]`-attributed method instead of routing an HTTP
request. Under Tenma `serve()` starts the server instead, so tasks run with `php`.

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
$app->serve();
```

```bash
php index.php users:sync
tenma index.php users:sync   # under Tenma: runs the task in a Tenma task (Postgres pool, fetch) instead of serving
```

---

## Plugins

Anything beyond the core (translations, LDAP, sessions, rate limiting, ...) is a separate Composer
package registered with one line before `attach()`:

```php
$app->plugin(new \Waypoint\I18n\I18nPlugin());
$app->configure(fn (\Waypoint\I18n\I18nOptions $i18n) => $i18n->default = 'de');
$app->attach([...]);
```

A plugin is one class implementing `Waypoint\Plugin\Plugin` (extend `PluginBase` for the defaults). It
declares what it adds and does nothing else:

| Declares | Effect |
|---|---|
| `classes()` | Controllers and `#[Manager]` classes attached alongside the app's own. |
| `middlewares()` | App-level middlewares, added at `attach()`. |
| `boot(...)` | Optional; runs once at `attach()`: read its Options class, bind services. Each parameter gets what its type names: `boot(Container $container, I18nOptions $options)`. |
| `cacheInputs()` | `{path => mtime}` of further files whose change must rebuild the route cache; the plugin's own class and its hooks' are watched already. |
| `hooks()` | Objects implementing any of the hook interfaces below. |

| Hook (`Waypoint\Plugin\...`) | When | Typical use |
|---|---|---|
| `Endpoint` | Before routing, after static files. Runs without route guards: no `#[Authenticated]`, no CSRF check, so an endpoint that changes state or shows private data checks the request itself. | Health, metrics, an SSO callback. |
| `Fallback` | When no route matched, before the 404; the app's routes always win. No route guards, like `Endpoint`; response hooks run with an empty plan. | Pages of a CMS, short links. |
| `RouteAttributeCompiler` | At compile time, per route; result cached under the plugin's name. | A `#[RateLimit]` attribute. |
| `Guard` | Per request before the controller, after auth/CSRF; gets that route's compiled data (`[]` if none). | Enforce the attribute. |
| `ResponseHook` | After the result was rendered. Not when a guard or the controller threw: the exception handler writes that response afterwards, so count errors in an app-level middleware or exception handler. | Audit, metrics, idempotency store. |
| `ArgumentBinder` | Own parameter attributes/types for controller methods, asked after the core attributes and before the implicit scalar binding. | `#[File]`, a table request. |
| `Renderer` | Own return types, asked after `View`/`Redirect`. | Exports, PDFs. |
| `OpenApiExtension` | Describes the plugin's bound parameters and rendered return types for the spec; without it they show as `string`/`object`. | `text/csv` for an export. |
| `ViewHelper` | Methods templates can call as `$this->name()`; markup comes back as `Waypoint\Html`, or `{{ }}` escapes it. | `$this->t('key')`. |
| `ClientAsset` | CSS/JS files shipped by the plugin, served content-hashed; render with `$this->pluginAssetTags()`. | A table component. |

State that belongs to one request (a locale, a user, queued messages) lives in a `Waypoint\Support\FiberLocal`,
not in a property of the plugin's service: under Tenma several requests run interleaved in one process, and
the handler of an upgraded request (WebSocket, stream) only gets back what is in a `FiberLocal`. Set it at the
start of every request rather than resetting it afterwards, since a reset in `after()` runs before that handler.

Rules: `requires()` names plugins that must come first; two plugins with the same `name()` or the
same view helper are a boot error; plugins never reach into each other or into the core's internals.
The core also exposes `Container::bind(Interface::class, $service)` and `Waypoint\Validation\Messages`
(translates the validator's English messages, which double as catalog keys) for plugins to hook into. A plugin
that knows who may do what binds a `Waypoint\PermissionProvider`: `#[Permissions(...)]` asks it unless the app set
`PermissionOptions::$provider` itself.

Available plugins:

| Package | Adds |
|---|---|
| `systonia/waypoint-i18n` | `$this->t('Source text')` with JSON catalogs kept by CLI tasks, per-request locale, translated validation errors. |
| `systonia/waypoint-flash` | Flash messages across a redirect, shown once, with and without JavaScript; the reference for the client plugin API. |
| `systonia/waypoint-charts` | Bar and line charts as server-rendered SVG with a data table; `$this->chart($chart)` in views or `return $chart;` as `image/svg+xml`. |
| `systonia/waypoint-export` | `return Sheet::file('users.xlsx')->columns([...])->rows($rows);` downloads CSV, JSON Lines, HTML, XML with schema, XLSX or ODS by extension; `$this->sheet()` for an inline table. |
| `systonia/waypoint-export-fixed` | Fixed-width record files (`#[Fixed]` layouts, Windows-1252/EBCDIC) as a sub-plugin of waypoint-export; the reference for plugins that extend a plugin. |

---

## Testing

`Waypoint\Testing\TestCase` (needs `phpunit/phpunit` as a dev dependency) drives a real request through
the app with `Request::fromParts()` and `App::handle()`, so nothing goes through the superglobals or
`header()`/`echo`. Every test gets a fresh `Waypoint` and clean superglobals; cookies a test puts into
`$_COOKIE` and `$_SERVER['HTTPS']` still apply as defaults:

```php
use Waypoint\Testing\TestCase;

final class LoginTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $app = $this->app();          // Waypoint::create()
        $app->configure(fn (CsrfOptions $csrf) => $csrf->secret = str_repeat('x', 32));
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
  `withHeader()`, `withCookie()`, `withJwt($payload)`, `withCsrf()` (a valid cookie + header pair), `withHttps()`,
  `asNavigation()` (what a browser sends), then `send()`.
- Responses: `status()`, `header()`, `cookie()`, `body()`, `json('errors.email')`, and fluent
  `assertStatus/Ok/Redirect/Header/HeaderMissing/ContentType/Cookie/See/DontSee/Body/Json/JsonPath`.
- `tempDir()` gives a directory that is removed after the test, e.g. for `FileSystemOptions::$cacheDirectory`.

The framework's own suite and every plugin run on this kit.

---

## Types and PHPStan

Every fact about a type is written once. Native types first: an array shape becomes a small readonly class. An
array Waypoint sees (a DTO property, a route result) is typed by its attribute only, `#[ListOf]` or `#[Returns]`, with
no docblock; an array Waypoint does not see (a repository result) by its PHPDoc type only (`@return list<Order>`).

The package's `extension.neon` turns the attributes into real types: reading a `#[ListOf(OrderLine::class)]`
property gives `list<OrderLine>`, calling a `#[Returns(type: Product::class, list: true)]` method `list<Product>`
(nullable when the native type is). What is returned from a `#[Returns]` method or assigned to a `#[ListOf]`
property (also as a constructor argument of a promoted parameter) must fit: errors `waypoint.returns` and
`waypoint.listOf`.

Database rows and decoded JSON are `mixed`. `Waypoint\Support\Row` reads them with typed getters instead of casts
or `...$row` spreading, and throws an `UnexpectedValueException` naming the key and the type found:

```php
use Waypoint\Support\Row;

$row = new Row($this->db->first('SELECT id, status, lines FROM orders WHERE id = $1', [$id]));
$order = new Order(
    id: $row->int('id'),
    status: $row->enum('status', OrderStatus::class),
    lines: array_map(OrderLine::of(...), $row->rows('lines')),
);
$tags = Row::of($response->json())->list('tags', 'string');   // list<string>
```

The getters are `int`, `string`, `float` (also an int or numeric string, as Postgres sends `numeric`), `bool`,
`enum`, their `nullable` variants (null for a null value or a missing key), `row`, `rows`, `list`, `map` and `has`;
`Row::of()` and `Row::each()` take decoded JSON. More in the guide [Types and PHPStan](docs/guides/types.md).

## Environment Variables

`Waypoint\Environment` reads env vars/`.env`/JSON config files through whatever `EnvironmentOptions` the
container holds. It is resolved through the container like any other service, so it's `#[Inject]`-able into
a controller/service. `get($key)`/`isDev()` lazily call `load()` on first use if nothing has been loaded
yet. `load(?string $dir = null)` reads, from `$dir`, each of these in order; later ones overlay (add
to/override) earlier ones:

1. `config.json`, then `config.$APP_ENV.json` (committed defaults)
2. `.env`, then `.env.$APP_ENV`
3. System env vars, then `$_ENV`, then `$_SERVER` (the real environment wins over the files)
4. `$opts->localConfigFile` (default `config.local.json`), see below

A value given with `set($key, $value)` wins over all of them, also when it was set before the first or a
later `load()`.

`get($key, $default)` returns the value as read (a string from `.env`, the JSON type from a JSON file);
`string()`, `int()`, `float()` and `bool()` (`"false"`, `"0"`, `"off"` are `false`) convert it, and without a
default throw a `RuntimeException` naming a missing key: `$env->int('PAGE_SIZE', 20)`, `$env->string('DATABASE_URL')`.

`$dir` **defaults to the current working directory** (the project root, for how PHP is normally invoked:
`php -S ... -t public`, `composer`/CLI tasks, most process managers), not this file's own location.
Call it explicitly with an absolute path if your entry point's cwd isn't reliable in your deployment,
either directly or via `configure()`:

```php
use Waypoint\Options\EnvironmentOptions;

// e.g. from public/index.php, where the project root is one level up
$app->configure(fn (EnvironmentOptions $env) => $env->load(dirname(__DIR__)));
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

`APP_ENV` defaults to `development` under the `cli`/`cli-server` SAPI and under `tenma --watch` when unset
anywhere. Every other server, and Tenma without `--watch` (CLI tasks run as `tenma index.php <task>` included), defaults to `production`: the request never decides it, since Apache and Caddy fill `SERVER_NAME`
from the client's `Host` header. A local FPM setup sets `APP_ENV=development` itself. Request headers
(`HTTP_*`, which FPM puts into `$_SERVER` and `$_ENV`, and CGI into the process environment) are never read as
environment: no source's `HTTP_*` keys are.
The default 500 response never shows an unhandled exception's message, in development neither: it goes to
the log.

The JSON files are flat `{"KEY": value, ...}` objects (a value keeps its JSON type, such as bool, int or array,
unlike `.env`'s always-string values). `config.json`/`config.$APP_ENV.json` are meant to be committed
(safe defaults, or demo/dev-only values); `$opts->localConfigFile` is meant to be **gitignored**: a
per-developer override for real secrets or local-only values, read last so it wins over everything else,
without having to write real system/shell env vars just to keep them out of version control:

```php
$app->configure(function (EnvironmentOptions $opts) {
    $opts->localConfigFile = 'config.mine.json'; // default: 'config.local.json'
    // or: $opts->localConfigFile = null;        // disable this layer entirely
});
```

Missing files at any layer are silently skipped; nothing is required to exist.

---

## Route/DI Compilation Caching & Production Performance

`attach()` normally discovers routes/DI/attributes via Reflection on every boot and keeps the result
in memory. Without a `cacheDirectory` nothing is written to or `require`d from disk (there is
deliberately no fallback to the system temp directory, where anyone on the machine could plant a file
that would then run as code). `FileSystemOptions`, configured like any other Options class via
`configure()`, compiles that once to disk instead; set it in production:

```php
use Waypoint\Options\FileSystemOptions;

$app->configure(function (FileSystemOptions $fs) {
    $fs->cacheDirectory = __DIR__ . '/var/cache';

    // Re-validates the cache (reflects every controller, compares file mtimes)
    // on every request: safe default, but costs a Reflection pass per boot.
    $fs->cacheValidate = true;

    // "Trust mode": skips validation entirely and just reads the compiled
    // cache. You are responsible for clearing/rebuilding this directory on
    // deploy: a stale cache keeps serving the old routes until it's rebuilt.
    // $fs->cacheValidate = false;
});
```

Trust mode reads the compiled route/service/attribute data once per request (shared between the
router and the DI container setup) instead of re-deriving it via Reflection, and skips the file-mtime
freshness check. This is the single largest throughput lever available: benchmarked well over an
order of magnitude faster than uncached Reflection-based discovery under repeated requests.

The cache directory holds `routes.php` (route and task plans, the service list, plugin route data),
`attributes.php` (every controller attribute, for the OpenAPI generator), `meta.php` (the mtimes the
cache was built from) and `assets/` (content-hashed view CSS/JS, `waypoint.js`, plugin assets). With
`cacheValidate = true` the cache is rebuilt as soon as any controller file, base controller file or
trait they use, view asset, plugin class, hook class or `cacheInputs()` entry, the client bundle or the framework itself
changes, or a controller or plugin is added or removed; in trust mode
you clear the directory on deploy.

**Also enable OPcache** (`opcache.enable=1`, and `opcache.enable_cli=1` if you're benchmarking or
running under `php -S`) in any environment where you care about request latency. In our benchmarks,
OPcache being off was consistently the single dominant cost, well beyond anything Waypoint itself does. Trust-mode caching and OPcache are
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

An unconfigured Options class still resolves; every setting below already shows its default.

### [CompressionOptions](#gzip-compression)

| Setting | Default | Description |
|---|---|---|
| `enabled` | `true` | Master on/off switch for gzip response compression everywhere. |
| `minBytes` | `1024` | A response body shorter than this (bytes, before compression) is always sent uncompressed. |

### [CorsOptions](#cors)

| Setting | Default | Description |
|---|---|---|
| `allowOrigin` | `null` | `Access-Control-Allow-Origin` value. Unset omits the header entirely, so CORS is off by default. |
| `allowMethods` | `null` | `Access-Control-Allow-Methods` value, e.g. `'GET, POST, OPTIONS'`. |
| `allowHeaders` | `null` | `Access-Control-Allow-Headers` value, e.g. `'Content-Type, Authorization'`. |
| `exposeHeaders` | `null` | `Access-Control-Expose-Headers`: response headers a cross-origin caller may read. |
| `maxAge` | `null` | How long (seconds) a browser may cache a preflight `OPTIONS` response. |
| `allowCredentials` | `false` | Sends `Access-Control-Allow-Credentials: true` when `true`; otherwise the header is omitted. |

### [CsrfOptions](#csrf-protection)

| Setting | Default | Description |
|---|---|---|
| `secret` | *(required, uninitialized)* | HMAC signing key for issued tokens. Unconfigured (the default), CSRF checking is off entirely; every request passes through unchecked. |
| `ttl` | `3600` | How long an issued token stays valid, in seconds; also the CSRF cookie's own Max-Age. |
| `cookieName` | `'csrf_token'` | Name of the double-submit cookie the token is issued under. Deliberately not HttpOnly: client-side JS must be able to read it. |
| `headerName` | `'X-CSRF-Token'` | Request header checked first for the submitted token (the AJAX/fetch path). |
| `fieldName` | `'_csrf'` | Request body field checked when the header isn't present (the classic no-JS `<form>` path). |
| `cookieSecure` | `null` | The CSRF cookie's own `Secure` attribute; `null` follows the request (`$req->https`). |
| `cookieSameSite` | `'Lax'` | The CSRF cookie's own `SameSite` attribute. |

### [EnvironmentOptions](#environment-variables)

| Setting | Default | Description |
|---|---|---|
| `localConfigFile` | `'config.local.json'` | Gitignored, per-developer override file, read last so it wins over everything else. `null` disables this layer. |

Configuration mainly happens through `load(?string $dir = null)`, not a plain property; see
[Environment Variables](#environment-variables) for the full `.env`/`config.json` layering it drives.

### [FileSystemOptions](#routedi-compilation-caching--production-performance)

| Setting | Default | Description |
|---|---|---|
| `cacheDirectory` | `null` (no disk cache, compiled in memory) | Where the compiled route/DI/attribute cache and compiled view assets are written. See [Route/DI Compilation Caching](#routedi-compilation-caching--production-performance). |
| `publicDirectory` | `null` (disabled) | A plain directory served as static files ahead of routing. See [Static Files](#static-files). |
| `assetsPath` | `'/assets'` | URL path prefix compiled view/layout CSS/JS is served under, e.g. `GET {assetsPath}/{hash}.css`. See [Views](#views). |
| `cacheValidate` | `true` | Re-verify the cache is fresh (reflect + compare mtimes) on every request; `false` is "trust mode". |

### [JWTOptions](#jwt-authentication)

| Setting | Default | Description |
|---|---|---|
| `secret` | *(required, uninitialized)* | Signing secret. Accessing it unconfigured throws immediately rather than signing with a guessable default. |
| `ttl` | `3600` | Token lifetime, in seconds. |
| `tokenType` | `'Bearer'` | Expected prefix on the `Authorization` header value. |
| `header` | `'Authorization'` | Request header the token is read from. |
| `cookieName` | `null` (disabled) | Cookie name to additionally read the token from when the header didn't produce one, so a signed-in session survives a plain page load, not just fetch()/XHR calls. |
| `loginRedirectUrl` | `null` (disabled) | Where an `UnauthorizedException` (e.g. `#[Authenticated]` with no/expired JWT) sends a browser page navigation (`Sec-Fetch-Mode: navigate`) as a `302` instead of `401` JSON. Any other `fetch()`/XHR call still gets the `401`. |

### [LoggerOptions](#logging)

| Setting | Default | Description |
|---|---|---|
| `levels` | `[]` (→ all levels) | PSR-3 levels this logger accepts, e.g. `['error', 'critical']`. |
| `name` | `null` | Optional channel/name for this logger. |

Loggers are registered through `add($logger, $options)` / `addMono($channel, $handler, $levels)`, not
plain properties; see [Logging](#logging).

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
| `securitySchemes` | `[]` | `components.securitySchemes` entries; pair with `#[Throws]`/route-level auth as needed. |
| `security` | `[]` | Top-level `security` requirement entries. |
| `externalDocs` | `null` | Optional `{description, url}` external documentation link. |

### [RendererOptions](#views)

| Setting | Default | Description |
|---|---|---|
| `directory` | `null` | Where view/layout `.php` templates (and their sibling `.css`/`.js`) live. |
| `layout` | `'_Layout'` (no extension, matches nothing) | Default layout template; pass an explicit `.php` name, or every render falls back to bare view content. |

### [SecurityHeaderOptions](#security-headers)

| Setting | Default | Description |
|---|---|---|
| `contentTypeOptionsEnabled` / `contentTypeOptions` | `true` / `'nosniff'` | `X-Content-Type-Options`. |
| `frameOptionsEnabled` / `frameOptions` | `true` / `'DENY'` | `X-Frame-Options`; set `'SAMEORIGIN'` to allow same-origin framing. |
| `referrerPolicyEnabled` / `referrerPolicy` | `true` / `'strict-origin-when-cross-origin'` | `Referrer-Policy`. |
| `hstsEnabled` / `hsts` | `true` / `'max-age=31536000; includeSubDomains'` | `Strict-Transport-Security`, only ever sent on a request that itself arrived over HTTPS, regardless of this flag. |
| `cspEnabled` / `csp` | `false` / `null` | `Content-Security-Policy`, opt-in only; a wrong one-size-fits-all default would break real pages instead of just failing open. |

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
into `src/Waypoint/UI/waypoint.js`. Commit that file with the change, CI fails when it is stale.
New features that go beyond the core belong in a plugin package (see [Plugins](#plugins)).

---

## License

Waypoint is licensed under either of [MIT](LICENSE-MIT) or
[Apache-2.0](LICENSE-APACHE), at your option.

---

## Contact

For questions or support, open an issue or contact the maintainer.

---

*Happy coding with Waypoint!*
