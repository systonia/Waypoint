<?php

namespace Waypoint;

use Closure;
use InvalidArgumentException;
use LogicException;
use ReflectionFunction;
use ReflectionNamedType;
use Throwable;
use Waypoint\Exceptions\{HttpException, UnauthorizedException};
use Waypoint\Http\{Request, Response};
use Waypoint\Options\{CompressionOptions, CorsOptions, CsrfOptions, FileSystemOptions, JWTOptions};
use Waypoint\Plugin\{Plugin, PluginRegistry};
use Waypoint\Routing\{PropertyInjector, ServiceDiscovery};
use Waypoint\Support\FiberLocal;

/**
 * The application: owns the container, the app-level middleware pipe, the
 * exception handlers, and (after attach()) the Router. Response::send() is
 * called exactly once per request, at the very end of handleHttp(), so every
 * middleware's after() can still shape the response. An exception from an
 * app-level middleware goes through the same handlers as one from a route.
 */
class App
{
    private Router $router;
    private Container $container;
    private PropertyInjector $injector;
    private PluginRegistry $plugins;

    /** @var callable[] */
    private array $middlewares = [];

    /** @var array<class-string, callable(Throwable, Request, Response): void> */
    private array $exceptionHandlers = [];

    public function __construct()
    {
        $this->container = new Container();
        $this->injector = new PropertyInjector($this->container);
        $this->plugins = new PluginRegistry();
        $this->registerDefaultExceptionHandlers();
    }

    /** Registers a plugin (see Waypoint\Plugin\Plugin); must happen before attach(). Order matters for hooks. */
    public function plugin(Plugin $plugin): void
    {
        if (isset($this->router)) {
            throw new InvalidArgumentException('plugin() must be called before attach().');
        }
        $this->plugins->add($plugin);
    }

    /**
     * Discovers and constructs every #[Inject]ed service, then builds the Router.
     * In trust mode (cacheValidate = false) the class list comes from the cached
     * routes.php, read once here and handed to Router so it isn't read twice.
     *
     * @param class-string[] $controllers
     */
    public function attach(array $controllers): void
    {
        $this->plugins->boot($this->container);
        foreach ($this->plugins->middlewares() as $middleware) {
            $this->use($middleware);
        }
        $controllers = [...$controllers, ...$this->plugins->classes()];

        $fileSystemOptions = $this->container->get(FileSystemOptions::class);
        $trusted = $fileSystemOptions->cacheDirectory !== null && !$fileSystemOptions->cacheValidate;
        $cachedData = $trusted ? (new FileSystem($fileSystemOptions))->loadCachedRouteData() : null;

        $cachedServices = $cachedData['services'] ?? null;
        $allClasses = is_array($cachedServices)
            ? array_values(array_filter($cachedServices, static fn(mixed $v): bool => is_string($v) && class_exists($v)))
            : ServiceDiscovery::discover($controllers);

        $this->initDependencyInjection($allClasses);
        $this->router = new Router($controllers, $allClasses, $this->container, $cachedData, $fileSystemOptions, $this->plugins);
    }

    /**
     * Constructs (or reuses an already-configured instance of) every class, registers all of
     * them, and only then wires #[Inject] properties -- so injection works regardless of
     * discovery order.
     *
     * @param class-string[] $classes
     */
    private function initDependencyInjection(array $classes): void
    {
        $instances = [];
        foreach ($classes as $cls) {
            if (!class_exists($cls)) {
                continue;
            }
            $instances[] = $this->container->get($cls);
        }
        foreach ($instances as $instance) {
            $this->injector->injectReflected($instance);
        }
    }

    /**
     * Adds an app-level middleware: `function (Request $req, Response $res, callable $next)`,
     * or a MiddlewareBase instance. An object gets its #[Inject] properties wired here, since
     * user code constructed it rather than the container.
     */
    public function use(callable $middleware): void
    {
        if (is_object($middleware)) {
            $this->injector->injectReflected($middleware);
        }
        $this->middlewares[] = $middleware;
    }

    /** Attaches the configured CorsOptions headers to every response and answers an OPTIONS preflight with a bare 204. */
    public function useCors(): void
    {
        $this->use(function (Request $req, Response $res, callable $next): mixed {
            foreach ($this->container->get(CorsOptions::class)->toHeaders() as $header => $value) {
                $res->withHeader($header, $value);
            }
            return $req->method === 'OPTIONS' ? $res->status(204) : $next($req, $res);
        });
    }

    /**
     * Decodes a bearer token from JWTOptions::$header (or, as a fallback, the JWTOptions::$cookieName cookie) into $req->jwt.
     * A cookie the browser sends on its own needs CSRF protection: with a cookie name but no CsrfOptions::$secret,
     * every request fails until one is configured, instead of quietly accepting cross-site requests.
     */
    public function useJwt(): void
    {
        $this->use(function (Request $req, Response $res, callable $next): mixed {
            if ($this->container->get(JWTOptions::class)->cookieName !== null && !isset($this->container->get(CsrfOptions::class)->secret)) {
                throw new LogicException('JWTOptions::$cookieName signs requests in by cookie, which needs CSRF protection: set CsrfOptions::$secret.');
            }
            $payload = JWT::fromRequestHeaders($req->headers);
            if ($payload === null) {
                $cookieName = $this->container->get(JWTOptions::class)->cookieName;
                $cookie = $cookieName !== null ? ($req->cookies[$cookieName] ?? null) : null;
                if ($cookie !== null) {
                    $payload = JWT::decode($cookie);
                }
            }
            if ($payload !== null) {
                $req->jwt = $payload;
            }
            return $next($req, $res);
        });
    }

    /** @codeCoverageIgnore php_sapi_name() reports 'cli' under the test runner too; both branches are tested directly. */
    public function run(): void
    {
        match (php_sapi_name()) {
            'cli' => $this->handleCli(),
            default => $this->handleHttp(),
        };
    }

    /** @codeCoverageIgnore exit()s with runCli()'s code. */
    public function handleCli(): void
    {
        exit($this->runCli());
    }

    /** The CLI task runner (`php index.php task-name ...`), returning the exit code instead of exiting. */
    public function runCli(): int
    {
        global $argv, $argc;

        if ($argc < 2) {
            fwrite(STDERR, "No command given\n");
            return 1;
        }

        try {
            $result = $this->router->executeTask(name: $argv[1], argv: $argv, argc: $argc);
            echo match (true) {
                is_string($result) => $result,
                // @codeCoverageIgnoreStart
                // every real task returns a string.
                is_scalar($result) => (string) $result,
                $result instanceof \Stringable => (string) $result,
                $result === null => '',
                default => json_encode($result) ?: '',
                // @codeCoverageIgnoreEnd
            };
        } catch (Throwable $e) {
            fwrite(STDERR, $e->getMessage() . "\n");
            return 1;
        }
        return 0;
    }

    /**
     * Handles the request in the superglobals and sends the answer through header()/echo.
     * Public so tests and unusual SAPIs can drive it directly.
     * @param string|null $rawBody Overrides php://input (the Testing kit's JSON requests).
     */
    public function handleHttp(?string $rawBody = null): void
    {
        $this->handle(Request::capture($rawBody))->send();
    }

    /**
     * Makes the app a request handler for Tenma: `Tenma::serve(3000, $app)`. See Tenma\Bridge.
     */
    public function __invoke(\Tenma\Http\Request $request): \Tenma\Http\Response|\Tenma\Http\WebSocketResponse|\Tenma\Http\StreamedResponse
    {
        return Tenma\Bridge::respond($this, $request);
    }

    /**
     * Runs $req through the middleware pipe, routing and exception handlers and returns the finalized Response
     * without sending it: for a runtime that hands over requests itself (Request::fromParts()) and writes
     * getStatus()/getHeaders()/getCookies()/getBody() out on its own.
     */
    public function handle(Request $req): Response
    {
        $res = (new Response($this->container->get(CompressionOptions::class)))->forRequest($req)->withRequestId($req->id);
        $this->container->get(RequestContext::class)->setRequestId($req->id);

        try {
            $handler = array_reduce(
                array_reverse($this->middlewares),
                fn(callable $next, callable $mw): callable => fn(Request $req, Response $res): mixed => $mw($req, $res, $next),
                fn(Request $req, Response $res): Response => $this->dispatch($req, $res)
            );
            try {
                $handler($req, $res);
            } catch (Throwable $e) {
                // An app-level middleware threw (a route's own exceptions were already handled in dispatch()).
                $this->handleException($e, $req, $res);
            }
            $this->container->get(Csrf::class)->followSession($res);
            if ($res->getUpgrade() !== null) {
                // The runtime runs the upgrade after the reset below; it restores what belongs to this request
                $res->keepContext(FiberLocal::capture());
            }
        } finally {
            // Per-request singleton state must not leak into a later request on a persistent worker.
            $this->container->get(RequestContext::class)->setRequestId(null);
            $this->container->get(Csrf::class)->reset();
        }
        return $res->finalize();
    }

    private function dispatch(Request $req, Response $res): Response
    {
        try {
            $this->router->dispatch(uri: $req->path, httpMethod: $req->method, req: $req, res: $res);
        } catch (Throwable $e) {
            $this->handleException($e, $req, $res);
        }
        return $res;
    }

    /**
     * Registers (or overrides) the handler for an exception class, its subclasses, or an
     * interface. A handler builds $res; it must not need to send it (send() is idempotent, so
     * doing so anyway is harmless).
     *
     * @param class-string $exceptionClass
     * @param callable(Throwable, Request, Response): void $handler
     */
    public function useExceptionHandler(string $exceptionClass, callable $handler): void
    {
        $this->exceptionHandlers[$exceptionClass] = $handler;
    }

    private function registerDefaultExceptionHandlers(): void
    {
        // Any HttpException -> its RFC 9457 Problem Details.
        $this->useExceptionHandler(HttpException::class, function (Throwable $e, Request $req, Response $res): void {
            if ($e instanceof HttpException) {
                $this->writeProblemDetails($res, $e->getStatusCode(), $e->toProblemDetails());
            }
        });

        // 401: a browser page navigation goes to JWTOptions::$loginRedirectUrl (if set); any
        // fetch()/XHR call, or a non-browser client, gets the 401 body it can act on.
        $this->useExceptionHandler(UnauthorizedException::class, function (Throwable $e, Request $req, Response $res): void {
            if (!$e instanceof UnauthorizedException) {
                // @codeCoverageIgnoreStart
                return;
                // @codeCoverageIgnoreEnd
            }
            $loginRedirectUrl = $this->container->get(JWTOptions::class)->loginRedirectUrl;
            if ($loginRedirectUrl !== null && self::isBrowserNavigation($req)) {
                $res->status(302)->withHeader('Location', $loginRedirectUrl);
                return;
            }
            $this->writeProblemDetails($res, $e->getStatusCode(), $e->toProblemDetails());
        });

        // Anything else: logged, and a generic 500 that never leaks the message (a file path,
        // a query, a driver error) -- not even in development. Override via useExceptionHandler().
        $this->useExceptionHandler(Throwable::class, function (Throwable $e, Request $req, Response $res): void {
            $this->container->get(Logger::class)->error($e->getMessage(), ['exception' => $e]);
            $this->writeProblemDetails($res, 500, ['type' => 'about:blank', 'title' => 'Internal Server Error', 'status' => 500]);
        });
    }

    /** @param array<string, mixed> $problem */
    private function writeProblemDetails(Response $res, int $status, array $problem): void
    {
        $body = json_encode($problem);
        $res->status($status)
            ->withHeader('Content-Type', 'application/problem+json')
            ->write($body !== false ? $body : '{"type":"about:blank","title":"Internal Server Error","status":500}');
    }

    /** Sec-Fetch-Mode: navigate -- a top-level navigation, where only a real 3xx can react; absent (curl, old browser) counts as "no". */
    private static function isBrowserNavigation(Request $req): bool
    {
        foreach ($req->headers as $name => $value) {
            if (strcasecmp($name, 'Sec-Fetch-Mode') === 0) {
                return $value === 'navigate';
            }
        }
        return false;
    }

    /** The registered handler writes the error response; a WebSocket or stream handed over before the throw is dropped. */
    private function handleException(Throwable $e, Request $req, Response $res): void
    {
        $res->withoutUpgrade();
        ($this->resolveExceptionHandler($e))($e, $req, $res);
    }

    /** Exact class, then parents, then interfaces (Throwable itself skipped so it stays the final fallback). */
    private function resolveExceptionHandler(Throwable $e): callable
    {
        $candidates = [get_class($e), ...class_parents($e), ...array_diff(class_implements($e), [Throwable::class])];
        foreach ($candidates as $class) {
            if (isset($this->exceptionHandlers[$class])) {
                return $this->exceptionHandlers[$class];
            }
        }
        return $this->exceptionHandlers[Throwable::class];
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @return T|null
     */
    public function get(string $class): ?object
    {
        try {
            return $this->container->get($class);
        } catch (Throwable) {
            return null;
        }
    }

    public function getRouter(): Router
    {
        return $this->router;
    }

    public function hasRouter(): bool
    {
        return isset($this->router);
    }

    public function getContainer(): Container
    {
        return $this->container;
    }

    /** Calls $config with the container's instance of each Options class its parameters are typed with. */
    public function configure(callable $config): void
    {
        $args = [];
        foreach ((new ReflectionFunction(Closure::fromCallable($config)))->getParameters() as $param) {
            $type = $param->getType();
            if (!$type instanceof ReflectionNamedType || $type->isBuiltin() || !class_exists($type->getName())) {
                throw new InvalidArgumentException('Closure parameters must be type-hinted with a non-builtin Options class');
            }
            $args[] = $this->container->get($type->getName());
        }
        $config(...$args);
    }
}
