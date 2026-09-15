[![Latest Version](https://img.shields.io/packagist/v/systonia/waypoint.svg)](https://packagist.org/packages/systonia/waypoint)
![Build](https://github.com/Systonia/Waypoint/actions/workflows/build.yaml/badge.svg)
[![PSR-3 Compatible](https://img.shields.io/badge/PSR--3-compatible-brightgreen.svg)](https://www.php-fig.org/psr/psr-3/)
[![PSR-4 Compatible](https://github.com/Systonia/Waypoint/actions/workflows/psr-4.yaml/badge.svg)](https://www.php-fig.org/psr/psr-4/)
![PHPStan](https://github.com/systonia/Waypoint/actions/workflows/phpstan.yaml/badge.svg)
![PHPStan Level](https://img.shields.io/badge/PHPStan-level%206-brightgreen)
![PHP Version](https://img.shields.io/badge/PHP-8.5-blue)
![License](https://img.shields.io/github/license/Systonia/Waypoint)

# Waypoint

A lightweight PHP framework for building APIs and progressively enhanced UIs, with attribute-based
routing and partial HTML views over AJAX.

---

## Features

- Attribute-based routing and controllers (`#[Get]`/`#[Post]`/`#[Put]`/`#[Patch]`/`#[Delete]`)
- Simple, reachability-based dependency injection via `#[Inject]` (no separate "service" attribute)
- Request validation attributes (`#[NotBlank]`, `#[Email]`, `#[Length]`, `#[Regex]`)
- An explicit exception-handler registry, with sensible defaults out of the box
- Middleware pipeline, including per-route middleware via `#[Middleware(Class::class)]`
- Built-in CORS support
- JWT authentication (`useJwt()`), with a required (never-defaulted) signing secret
- An HTML-rendering MVC view layer, with layouts, sections, and automatic scoped CSS/JS per view
- Static file serving from a configured public directory
- Gzip response compression, size-thresholded, with an opt-out `#[NoGzip]` attribute per controller/route
- PSR-3 compatible logging (plug in any PSR-3 logger, or use `LoggerOptions::addMono()` for Monolog)
- CLI task runner (`#[Manager]`/`#[Task]`), for the same app to serve HTTP and run background jobs
- A complete OpenAPI 3.1.0 generator, driven by a compiled attribute cache rather than live reflection
- Optional route/DI compilation caching with an opt-in "trust mode" for production deploys

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
$app->attach([
    HelloController::class,
    Waypoint\OpenAPI\OpenAPIController::class, // optional: mounts /openapi/spec.json, swagger.html
]);

$app->run();
```

`attach()` discovers every class reachable from your controllers via `#[Inject]` (see
[Dependency Injection](#dependency-injection) below), wires up the router and container, and installs
the built-in `X-Waypoint-Accept: partial` handling. `run()` branches on `php_sapi_name()`: under a real
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
use Waypoint\Attributes\{NotBlank, Email, Length, Regex};

class CreateProductDTO
{
    #[NotBlank]
    public string $name = '';

    #[Email]
    public ?string $contactEmail = null;

    #[Length(min: 2, max: 12)]
    #[Regex(pattern: '/^[A-Z0-9\-]+$/')]
    public string $sku = '';
}
```

A failing DTO throws `ValidationException`, which the default exception handler turns into a `422`
with `{"error": "...", "details": {...}}`.

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

Global middleware runs on every request, registered via `use()`:

```php
$app->use(function ($req, $res, $next) {
    // ... before
    $result = $next($req, $res);
    // ... after
    return $result;
});
```

`useCors()` and `useJwt()` (see below) are both just built-in middleware registered this way.

Per-route middleware is declared with `#[Middleware(Class::class)]` directly on a
controller method (repeatable). The class is resolved through the container at dispatch time — so it
can itself use `#[Inject]` — rather than being instantiated directly:

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

Waypoint registers default handlers for its own exception types out of the box:

| Exception                | Status |
|---------------------------|--------|
| `ValidationException`     | 422 (body includes `details`, the per-field/per-index errors) |
| `ForbiddenException`      | 403 |
| `UnauthorizedException`   | 401 |
| `NotFoundException`       | 404 |
| Any other `Throwable`     | 500 (message hidden unless `Environment::isDev()`) |

Register your own handler — for an exact exception class, or anything further up its
class/interface hierarchy — with `useExceptionHandler()`. The most specific registered handler wins:
exact class match first, then parent classes, then implemented interfaces, then the `Throwable`
catch-all.

```php
use Waypoint\Http\{Request, Response};

$app->useExceptionHandler(MyDomainException::class, function (MyDomainException $e, Request $req, Response $res) {
    $res->status(409)
        ->withHeader('Content-Type', 'application/json')
        ->write(json_encode(['error' => $e->getMessage()]))
        ->send();
});
```

Calling `useExceptionHandler()` again for the same class overrides the previous handler — including
the built-in defaults above.

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

A view or layout's sibling `.css`/`.js` is discovered automatically (same basename, same directory,
either role) — nothing to register. Compiled, content-hashed copies are written under
`{cacheDirectory}/assets/` (see [Route/DI Compilation Caching](#routedi-compilation-caching--production-performance)
below) and served through `GET /assets/{hash}.css`/`.js` with a year-long, immutable `Cache-Control`
header.

Passing `partial: true` (typically driven by a request header your client sets — `$req->acceptPartial`
reflects the built-in `X-Waypoint-Accept: partial` handling `attach()` installs) skips the layout
entirely and renders just the view. The response also carries `X-Waypoint-View-Name`/`-Css`/`-Js`
headers describing what was rendered, for a client-side router to apply without a full page reload.

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

Mount `Waypoint\OpenAPI\OpenAPIController` in your `attach()` call to get:

- `GET /openapi/spec.json` — the generated OpenAPI 3.1.0 document
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

---

## Requirements

- PHP 8.4+
- Composer

---

## Contributing

Contributions welcome! Please open issues or pull requests on GitHub.

---

## License

MIT License

---

## Contact

For questions or support, open an issue or contact the maintainer.

---

*Happy coding with Waypoint!*
