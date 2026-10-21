---
title: "App"
description: "The application: owns the container, the app-level middleware pipe, the exception handlers, and (after attach()) the Router."
order: 100
generated: true
---

`Waypoint\App` · class · namespace [`Waypoint`](index.md)

```php
class App
```

The application: owns the container, the app-level middleware pipe, the
exception handlers, and (after attach()) the Router. Response::send() is
called exactly once per request, at the very end of handleHttp(), so every
middleware's after() can still shape the response. An exception from an
app-level middleware goes through the same handlers as one from a route.

## Methods

### __construct()

```php
public function __construct()
```

### plugin()

```php
public function plugin(\Waypoint\Plugin\Plugin $plugin): void
```

Registers a plugin (see Waypoint\Plugin\Plugin); must happen before attach(). Order matters for hooks.

**Parameters**

- `$plugin` [`Plugin`](Plugin/Plugin.md)

### attach()

```php
public function attach(array $controllers): void
```

Discovers and constructs every #[Inject]ed service, then builds the Router.
In trust mode (cacheValidate = false) the class list comes from the cached
routes.php, read once here and handed to Router so it isn't read twice.

**Parameters**

- `$controllers` `class-string[]`

### use()

```php
public function use(callable $middleware): void
```

Adds an app-level middleware: `function (Request $req, Response $res, callable $next)`,
or a MiddlewareBase instance. An object gets its #[Inject] properties wired here, since
user code constructed it rather than the container.

**Parameters**

- `$middleware` `callable`

### useCors()

```php
public function useCors(): void
```

Attaches the configured CorsOptions headers to every response and answers an OPTIONS preflight with a bare 204.

### useJwt()

```php
public function useJwt(): void
```

Decodes a bearer token from JWTOptions::$header (or, as a fallback, the JWTOptions::$cookieName cookie) into $req->jwt.
A cookie the browser sends on its own needs CSRF protection: with a cookie name but no CsrfOptions::$secret,
every request fails until one is configured, instead of quietly accepting cross-site requests.

### run()

```php
public function run(): void
```

### serve()

```php
public function serve(?int $port = null, array|string|null $static = null): void
```

The one line at the end of index.php, whatever runs it. Under Tenma it starts the server on $port, or on
PORT from the Environment (8080 without one), and Tenma serves FileSystemOptions::$publicDirectory itself,
without PHP; started with arguments (`tenma index.php db:migrate`) it runs that CLI task instead, in a Tenma
task so it can wait on Postgres or fetch(), and exits with its code. Under FPM, php -S or the CLI it is run(),
and Waypoint serves that directory.

**Parameters**

- `$port` `?int`
- `$static` `string|array<string, string>|null`: Tenma's static mounts instead, e.g. `['/assets' => __DIR__ . '/public/assets']`

### handleCli()

```php
public function handleCli(): void
```

### runCli()

```php
public function runCli(): int
```

The CLI task runner (`php index.php task-name ...`), returning the exit code instead of exiting.

**Returns** `int`

### handleHttp()

```php
public function handleHttp(?string $rawBody = null): void
```

Handles the request in the superglobals and sends the answer through header()/echo.
Public so tests and unusual SAPIs can drive it directly.

**Parameters**

- `$rawBody` `string|null`: Overrides php://input (the Testing kit's JSON requests).

### __invoke()

```php
public function __invoke(\Http\Request $request): \Http\Response|\Http\WebSocketResponse|\Http\StreamedResponse
```

Makes the app a request handler for Tenma: `new \Http\Server(3000, $app)->start()`. See Tenma\Bridge.

**Parameters**

- `$request` `\Http\Request`

**Returns** `\Http\Response|\Http\WebSocketResponse|\Http\StreamedResponse`

### handle()

```php
public function handle(\Waypoint\Http\Request $req): \Waypoint\Http\Response
```

Runs $req through the middleware pipe, routing and exception handlers and returns the finalized Response
without sending it: for a runtime that hands over requests itself (Request::fromParts()) and writes
getStatus()/getHeaders()/getCookies()/getBody() out on its own.

**Parameters**

- `$req` [`Request`](Http/Request.md)

**Returns** [`Response`](Http/Response.md)

### useExceptionHandler()

```php
public function useExceptionHandler(string $exceptionClass, callable $handler): void
```

Registers (or overrides) the handler for an exception class, its subclasses, or an
interface. A handler builds $res; it must not need to send it (send() is idempotent, so
doing so anyway is harmless).

**Parameters**

- `$exceptionClass` `class-string`
- `$handler` `callable(`[`Throwable`](https://www.php.net/manual/en/class.throwable.php)`, `[`Request`](Http/Request.md)`, `[`Response`](Http/Response.md)`): void`

### get()

```php
public function get(string $class): object
```

The container's instance of $class (a service, an Options class, Environment). A class it can't build throws
(ServiceNotFoundException, or ContainerException when its constructor fails); find() returns null instead.

**Parameters**

- `$class` `class-string<T>`

**Returns** `T`

**Template parameters**

- `T` of `object`

### find()

```php
public function find(string $class): ?object
```

Like get(), but null when the container can't build $class.

**Parameters**

- `$class` `class-string<T>`

**Returns** `T|null`

**Template parameters**

- `T` of `object`

### getRouter()

```php
public function getRouter(): \Waypoint\Router
```

The Router attach() built; only valid after attach().

**Returns** [`Router`](Router.md)

### hasRouter()

```php
public function hasRouter(): bool
```

Whether attach() has run.

**Returns** `bool`

### getContainer()

```php
public function getContainer(): \Waypoint\Container
```

The app's service container.

**Returns** [`Container`](Container.md)

### configure()

```php
public function configure(callable $config): void
```

Calls $config with the container's instance of each Options class its parameters are typed with.

**Parameters**

- `$config` `callable`
