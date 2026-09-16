<?php

namespace Waypoint;

use Throwable;
use ReflectionClass;
use ReflectionFunction;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use InvalidArgumentException;

use Waypoint\{Router};
use Waypoint\Options\{FileSystemOptions, JWTOptions, CorsOptions, CompressionOptions};
use Waypoint\Attributes\Inject;
use Waypoint\Http\{Request, Response};
use Waypoint\Exceptions\{HttpException, UnauthorizedException};

class App
{
    private Router $router;

    /** @var callable[] */
    private array $middlewares = [];
    private Container $container;

    /** @var array<class-string, callable(Throwable, Request, Response): void> */
    private array $exceptionHandlers = [];

    public function __construct()
    {
        $this->container = new Container();
        $this->registerDefaultExceptionHandlers();
    }

    /** @param class-string[] $controllers */
    public function attach(array $controllers): void
    {
        $fileSystemOptions = $this->container->get(FileSystemOptions::class);
        $fileSystem = new FileSystem($fileSystemOptions);

        // Trust mode ($fileSystemOptions->cacheValidate = false): read the
        // route cache once here (need its 'services' list to skip
        // re-discovering dependencies via Reflection), and pass the same
        // decoded data to Router so it doesn't `require` routes.php a
        // second time for the same information. Falls back to real
        // discovery/compilation on first boot, before any cache exists yet.
        $cachedData = ($fileSystemOptions->cacheDirectory !== null && !$fileSystemOptions->cacheValidate)
            ? $fileSystem->loadCachedRouteData()
            : null;

        $cachedServices = $cachedData['services'] ?? null;
        $allClasses = is_array($cachedServices)
            ? array_values(array_filter(
                $cachedServices,
                static fn(mixed $v): bool => is_string($v) && class_exists($v)
            ))
            : $this->discoverAllClasses($controllers);

        $this->initDependencyInjection($allClasses);

        $this->router = new Router($controllers, $allClasses, $this->container, $cachedData, $fileSystemOptions);

        $this->useWaypointAcceptHeader();
    }

    /**
     * Registers $middleware on the app-level pipe (see useCors()/useJwt()
     * for two built-in examples, or MiddlewareBase's own doc for how a
     * subclass instance -- e.g. `$app->use(new SomeMiddleware())` --
     * works here directly via __invoke()).
     *
     * An object middleware never goes through Router::resolveController()/
     * injectControllerProperties() the way a per-route #[Middleware(...)]
     * class does -- the caller already constructed it with `new` before
     * handing it here, so there's no class-string for the container to
     * resolve. #[Inject] properties on it still get wired though, via the
     * same injectServiceProperties() pass attach() already runs for
     * eagerly-constructed services -- the same "user built this directly,
     * not through the container" situation Router::injectViewProperties()
     * already solves for View, applied here. A plain closure has no
     * properties to find, so this is a harmless no-op for one.
     */
    public function use(callable $middleware): void
    {
        if (is_object($middleware)) {
            $this->injectServiceProperties(get_class($middleware), $middleware);
        }

        $this->middlewares[] = $middleware;
    }

    /**
     * Enables CORS handling: attaches whatever "Access-Control-*" headers
     * are configured on CorsOptions (see App::configure(CorsOptions)) to
     * every response, and short-circuits an OPTIONS preflight with a bare
     * 204. Resolved fresh from the container on every request, so
     * configure() can be called before or after useCors() itself.
     */
    public function useCors(): void
    {
        $this->use(function (Request $req, Response $res, callable $next): mixed {
            foreach ($this->container->get(CorsOptions::class)->toHeaders() as $header => $value) {
                $res = $res->withHeader($header, $value);
            }
            if ($req->method === 'OPTIONS') {
                // Not calling $next() already short-circuits the rest of
                // the chain (no dispatch, no controller) -- $res just
                // needs to carry the 204 status; App::handleHttp() sends
                // it, once, after the whole pipe unwinds, same as every
                // other response.
                return $res->status(204);
            }
            return $next($req, $res);
        });
    }

    private function useWaypointAcceptHeader(): void
    {
        $this->use(function (Request $req, Response $res, callable $next): mixed {
            // A direct case-insensitive scan for the one header we care
            // about, instead of allocating a whole lowercased copy of
            // every header via array_change_key_case() on every request.
            $req->acceptPartial = false;
            foreach ($req->headers as $name => $value) {
                if (strcasecmp($name, 'X-Waypoint-Accept') === 0) {
                    $req->acceptPartial = strcasecmp($value, 'partial') === 0;
                    break;
                }
            }
            return $next($req, $res);
        });
    }

    public function useJwt(): void
    {
        $this->use(function (Request $req, Response $res, callable $next) {
            // Ensure "Authorization" fallback is always applied
            $serverAuth = $_SERVER['AUTHORIZATION'] ?? null;
            if (is_string($serverAuth) && !isset($req->headers['Authorization'])) {
                $req->headers['Authorization'] = $serverAuth;
            }

            // Attempt to parse JWT
            $payload = JWT::fromRequestHeaders($req->headers);

            // A plain browser page navigation never sends a bearer
            // Authorization header -- only an explicit fetch()/XHR call
            // that sets one itself does. If JWTOptions::$cookieName is
            // configured, fall back to reading the same token out of that
            // cookie instead, so a signed-in session survives a full page
            // load too.
            if ($payload === null) {
                $cookieName = $this->container->get(JWTOptions::class)->cookieName;
                $cookieValue = $cookieName !== null ? ($_COOKIE[$cookieName] ?? null) : null;
                if (is_string($cookieValue)) {
                    $payload = JWT::decode($cookieValue);
                }
            }

            if ($payload !== null) {
                $req->jwt = $payload;
            }

            return $next($req, $res);
        });
    }

    /**
     * @codeCoverageIgnore Trivial dispatch on the real php_sapi_name(),
     * which always reports 'cli' under a CLI test runner too -- the actual
     * logic on both sides (handleCli()/runCli(), handleHttp()) is covered
     * directly instead.
     */
    public function run(): void
    {
        match (php_sapi_name()) {
            'cli' => $this->handleCli(),
            default => $this->handleHttp()
        };
    }

    /**
     * Runs the CLI task-runner path (reads the real process argv/argc) and
     * terminates the process with the resulting exit code. Public so a
     * custom front controller can invoke it directly; run() picks it
     * automatically based on php_sapi_name().
     *
     * @codeCoverageIgnore Calls exit() directly, which would kill the test
     * process -- runCli() carries the actual logic and is tested directly.
     */
    public function handleCli(): void
    {
        exit($this->runCli());
    }

    /**
     * Same as handleCli() but returns the exit code instead of calling
     * exit() itself, so the task-runner logic can be exercised from tests
     * (or embedded in a caller that wants to decide what to do with the
     * result) without terminating the process.
     */
    public function runCli(): int
    {
        global $argv, $argc;

        if ($argc < 2) {
            fwrite(STDERR, "No command given\n");
            return 1;
        }

        try {
            $result = $this->router->executeTask(name: $argv[1], argv: $argv, argc: $argc);
            // A task's return is `mixed` (see Router::executeTask()), but
            // every real task in this codebase returns a string -- the
            // other arms only exist so an oddly-written task can't fatal
            // echo itself, not because any of them are exercised in
            // practice.
            echo match (true) {
                is_string($result) => $result,
                // @codeCoverageIgnoreStart
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
     * Runs the HTTP request/response path against the real superglobals.
     * Public (not gated behind php_sapi_name() like run() is) so it can be
     * driven directly -- e.g. from tests, or a host environment that reports
     * a 'cli'-like SAPI name but is still serving an HTTP request.
     *
     * The one and only place Response::send() is called for a real HTTP
     * request -- every dispatch()/renderResult()/exception-handler code
     * path below only ever builds $res (status/headers/body) and returns;
     * none of them send it themselves anymore. That's what makes every
     * $app->use() middleware's after() hook (and, nested one level in,
     * every per-route #[Middleware(...)]'s after()) able to actually
     * affect what the client receives: by the time $handler($req, $res)
     * returns here, the *entire* chain -- app-level before()s, dispatch()
     * (itself wrapping the per-route middleware chain and the controller),
     * app-level after()s -- has already run, and $res reflects all of it.
     * A middleware that never calls $next() (a veto, e.g.
     * MiddlewareBase::before() returning false, or useCors()'s OPTIONS
     * short-circuit above) still reaches this same single send() call --
     * it just skips straight there without dispatch() ever running.
     */
    public function handleHttp(): void
    {
        $req = Request::capture();
        $res = (new Response($this->container->get(CompressionOptions::class)))->withRequestId($req->id);

        // Makes $req->id available to Logger::log() for the rest of this
        // request without threading it through every call site by hand
        // (see RequestContext) -- cleared again in finally so it can never
        // leak into a later request sharing this same App/container (e.g.
        // a persistent-worker deployment; a classic per-request PHP
        // process wouldn't need this, but it costs nothing here).
        $this->container->get(RequestContext::class)->setRequestId($req->id);

        try {
            $handler = array_reduce(
                array: array_reverse(array: $this->middlewares),
                callback: fn(callable $next, callable $mw): callable => fn(Request $req, Response $res): mixed => $mw($req, $res, $next),
                initial: fn(Request $req, Response $res): Response => $this->handle(req: $req, res: $res)
            );

            $handler($req, $res);
            $res->send();
        } finally {
            $this->container->get(RequestContext::class)->setRequestId(null);
            $this->container->get(Csrf::class)->reset();
        }
    }

    private function handle(Request $req, Response $res): Response
    {
        try {
            $this->router->dispatch(uri: $req->path, httpMethod: $req->method, req: $req, res: $res);
        } catch (Throwable $e) {
            ($this->resolveExceptionHandler($e))($e, $req, $res);
        }
        return $res;
    }

    /**
     * Register (or override) the handler invoked when a route/middleware throws
     * an exception of the given class (or one of its parents/interfaces, if no
     * exact match is registered). The handler is responsible for building $res
     * (status/headers/body) -- not for sending it; App::handleHttp() sends it
     * once, after the whole $app->use() pipe has unwound, same as every other
     * response.
     *
     * A handler is still free to call Response::send() itself if it really
     * wants to (it's idempotent -- see its own doc -- so App::handleHttp()'s
     * own trailing send() afterward is always a safe no-op either way, never
     * a double-send). That's no longer the recommended shape though: a
     * handler that only builds $res and returns is what actually keeps this
     * codebase's "exactly one send() call, at the very end" property intact
     * -- every default handler below follows that now.
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
        $this->useExceptionHandler(
            HttpException::class,
            // Typed Throwable, not HttpException, to actually satisfy
            // useExceptionHandler()'s callable(Throwable, ...) contract --
            // resolveExceptionHandler() only ever invokes a handler
            // registered under HttpException::class with a real
            // HttpException (looked up by the thrown exception's own
            // class/parents -- every one of this codebase's own HTTP-facing
            // exceptions, NotFoundException/ForbiddenException/
            // UnauthorizedException/ValidationException included, extends
            // it), so this narrows back immediately.
            function (Throwable $e, Request $req, Response $res): void {
                // @codeCoverageIgnoreStart
                // Unreachable in practice -- see the comment above.
                if (!$e instanceof HttpException) {
                    return;
                }
                // @codeCoverageIgnoreEnd
                $this->writeProblemDetails($res, $e->getStatusCode(), $e->toProblemDetails());
            }
        );

        // Exact-class match, so this overrides the generic HttpException
        // handler above for UnauthorizedException specifically (see
        // resolveExceptionHandler()) -- #[Authenticated]/#[Role]/
        // #[Permissions] (Router::dispatch()) throw it when $req->jwt is
        // null, and so can any app code (e.g. a login endpoint rejecting
        // bad credentials).
        $this->useExceptionHandler(
            UnauthorizedException::class,
            function (Throwable $e, Request $req, Response $res): void {
                // @codeCoverageIgnoreStart
                if (!$e instanceof UnauthorizedException) {
                    return;
                }
                // @codeCoverageIgnoreEnd

                $loginRedirectUrl = $this->container->get(JWTOptions::class)->loginRedirectUrl;
                if ($loginRedirectUrl !== null && self::isBrowserNavigation($req)) {
                    // A real top-level navigation (typed URL, bookmark,
                    // hard reload) -- no client-side JS has run yet to
                    // react to anything but a real 3xx, so only an actual
                    // redirect works here at all. An ordinary fetch()/XHR
                    // call (same-origin or cors, never 'navigate') always
                    // falls through to the plain 401 below instead,
                    // regardless of this option.
                    $res->status(302)->withHeader('Location', $loginRedirectUrl);
                    return;
                }

                $this->writeProblemDetails($res, $e->getStatusCode(), $e->toProblemDetails());
            }
        );

        $this->useExceptionHandler(
            Throwable::class,
            function (Throwable $e, Request $req, Response $res): void {
                $this->container->get(Logger::class)->error($e->getMessage(), ['exception' => $e]);
                // Deliberately never $e->getMessage()/a stack trace here,
                // not even in development (see Environment::isDev(), used
                // elsewhere in this codebase for exactly that kind of
                // gate) -- an *unexpected* exception's message can easily
                // contain internal details (a file path, a query, a raw
                // driver error) never meant to reach whoever triggered the
                // crash, and isDev() alone isn't a safe enough switch for
                // that. A project wanting more than this generic message
                // needs its own explicit useExceptionHandler(Throwable::class,
                // ...) override (registered after attach(), per this
                // method's own doc) -- an actual opt-in, not an implicit one.
                $this->writeProblemDetails($res, 500, [
                    'type' => 'about:blank',
                    'title' => 'Internal Server Error',
                    'status' => 500,
                ]);
            }
        );
    }

    /**
     * Builds an RFC 9457 ("Problem Details for HTTP APIs") response from
     * $problem -- HttpException::toProblemDetails()'s own shape, or the
     * generic 500 fallback above, which deliberately doesn't go through a
     * real HttpException (there's nothing to attach $problem's extra
     * members to; a plain array is simplest).
     *
     * @param array<string, mixed> $problem
     */
    private function writeProblemDetails(Response $res, int $status, array $problem): void
    {
        $body = json_encode($problem);
        $res->status($status)
            ->withHeader('Content-Type', 'application/problem+json')
            ->write($body !== false ? $body : '{"type":"about:blank","title":"Internal Server Error","status":500}');
    }

    /**
     * True only for a real top-level browser navigation (a typed URL,
     * bookmark, hard reload, or a plain <a href> the client never
     * intercepted) -- Sec-Fetch-Mode is a standard Fetch Metadata request
     * header every current browser sends automatically on every request,
     * with no app-side configuration needed: 'navigate' for exactly the
     * case above, 'same-origin'/'cors'/'no-cors' for any fetch()/XHR call
     * instead (what a client-side router, or a plain form's own JS
     * handler, actually sends) -- never 'navigate'. Missing entirely
     * (an older browser, or a non-browser client like curl) is treated as
     * "not a navigation", the safe default: a 401 Problem Details body
     * over a surprise redirect a non-browser caller can't follow anyway.
     */
    private static function isBrowserNavigation(Request $req): bool
    {
        foreach ($req->headers as $name => $value) {
            if (strcasecmp($name, 'Sec-Fetch-Mode') === 0) {
                return $value === 'navigate';
            }
        }
        return false;
    }

    /**
     * Finds the most specific registered handler for $e: exact class first,
     * then parent classes, then implemented interfaces, then the Throwable
     * catch-all (always registered by registerDefaultExceptionHandlers).
     *
     * Throwable itself is skipped in the interface loop: every real
     * Exception/Error implements it, so class_implements() always lists it
     * -- typically before any more specific interface the class also
     * implements (e.g. Psr\Container\NotFoundExceptionInterface) -- which
     * would otherwise make the interface loop match the Throwable::class
     * catch-all instead of a more specific interface handler a caller
     * registered via useExceptionHandler(). Throwable::class is still the
     * final fallback below, just no longer found early by accident.
     */
    private function resolveExceptionHandler(Throwable $e): callable
    {
        $class = get_class($e);
        if (isset($this->exceptionHandlers[$class])) {
            return $this->exceptionHandlers[$class];
        }

        foreach (class_parents($e) as $parent) {
            if (isset($this->exceptionHandlers[$parent])) {
                return $this->exceptionHandlers[$parent];
            }
        }

        foreach (class_implements($e) as $interface) {
            if ($interface === Throwable::class) {
                continue;
            }
            if (isset($this->exceptionHandlers[$interface])) {
                return $this->exceptionHandlers[$interface];
            }
        }

        return $this->exceptionHandlers[Throwable::class];
    }

    /**
     * Constructs every discovered class first and registers each with the
     * container, THEN wires up #[Inject] properties in a second pass --
     * never during construction itself. That order is what makes injection
     * work regardless of discovery order: by the time any instance's own
     * #[Inject] properties are resolved, every other discovered class is
     * already sitting in the container, ready to hand back, whether it
     * happens to depend on something discovered before or after it.
     * Without this second pass, a service injecting another service (e.g.
     * a repository injecting a shared DB connection) would construct fine
     * but its #[Inject] property would simply never get set -- exactly the
     * gap this closes; #[Inject] on a controller or #[Middleware] class
     * already worked, since Router applies this same wiring itself, per
     * request, via injectControllerProperties().
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
            // A class already registered (e.g. an Options class the app
            // configure()d before attach()) must not be clobbered with a
            // fresh, unconfigured instance just because something else also
            // #[Inject]s it -- reuse what's already there instead.
            if ($this->container->isRegistered($cls)) {
                $instances[$cls] = $this->container->get($cls);
                continue;
            }
            $instance = new $cls();
            $this->container->set($instance);
            $instances[$cls] = $instance;
        }

        foreach ($instances as $cls => $instance) {
            $this->injectServiceProperties($cls, $instance);
        }
    }

    /** @param class-string $cls */
    private function injectServiceProperties(string $cls, object $instance): void
    {
        // Same exclusion discoverAllClasses() already applies, and for the
        // same reason: these are supplied per-request by
        // Router::injectControllerProperties() (Request/Response's real
        // values don't exist yet at boot time; Request's constructor is
        // private besides), not eagerly constructed, container-managed
        // singletons. A controller with #[Inject] private Request $req
        // still works exactly as before -- Router re-wires it on every
        // dispatch, same as always; this pass simply never touches it.
        $notContainerManaged = [Request::class, Response::class, Router::class];

        $rc = new ReflectionClass($cls);
        foreach ($rc->getProperties() as $prop) {
            if (!$prop->getAttributes(Inject::class)) {
                continue;
            }
            $type = self::namedTypeOf($prop);
            if (!$type || in_array($type, $notContainerManaged, true) || !class_exists($type) || !$this->container->has($type)) {
                continue;
            }
            $prop->setValue($instance, $this->container->get($type));
        }
    }

    /**
     * @param class-string[] $controllers
     * @return class-string[]
     */
    private function discoverAllClasses(array $controllers): array
    {
        $all = $controllers;
        $queue = $controllers;
        $found = [];

        // Request/Response/Router are supplied per-request by
        // Router::injectControllerProperties() itself, not eagerly
        // constructed services -- discovering them here would make
        // initDependencyInjection() call `new Request()` etc., which fails
        // (Request's constructor is private by design; it's only ever
        // created via Request::capture()).
        $notContainerManaged = [Request::class, Response::class, Router::class];

        while ($queue) {
            $class = array_shift($queue);
            if (!class_exists($class) || isset($found[$class]))
                continue;
            $found[$class] = true;

            $rc = new ReflectionClass($class);

            foreach ($rc->getProperties() as $prop) {
                foreach ($prop->getAttributes(Inject::class) as $attr) {
                    $type = self::namedTypeOf($prop);
                    if ($type && class_exists($type) && !in_array($type, $notContainerManaged, true) && !in_array($type, $all, true)) {
                        $all[] = $type;
                        $queue[] = $type;
                    }
                }
            }

            $constructor = $rc->getConstructor();
            if ($constructor) {
                foreach ($constructor->getParameters() as $param) {
                    foreach ($param->getAttributes(Inject::class) as $attr) {
                        $type = self::namedTypeOf($param);
                        if ($type && class_exists($type) && !in_array($type, $notContainerManaged, true) && !in_array($type, $all, true)) {
                            $all[] = $type;
                            $queue[] = $type;
                        }
                    }
                }
            }
        }

        return $all;
    }

    /**
     * The declared type's name, or null if untyped -- or if it's a union/
     * intersection type, which (unlike a plain ReflectionNamedType) has no
     * single name to give. #[Inject] is only ever meaningful on a plain
     * single-class type anyway, so treating either case as "no type" is
     * exactly the right fallback, not just a type-checker workaround.
     */
    private static function namedTypeOf(ReflectionProperty|ReflectionParameter $member): ?string
    {
        $type = $member->getType();
        return $type instanceof ReflectionNamedType ? $type->getName() : null;
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

    public function configure(callable $config): void
    {
        $reflection = new ReflectionFunction(\Closure::fromCallable($config));
        $args = [];

        foreach ($reflection->getParameters() as $param) {
            $type = $param->getType();

            if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
                throw new InvalidArgumentException(
                    "Closure parameters must be type-hinted with a non-builtin Options class"
                );
            }

            $typeName = $type->getName();
            // @codeCoverageIgnoreStart
            // Every real Options class configure() is ever called with
            // exists -- this only guards the theoretical case of a typo'd
            // class name PHP still allows as a type hint, and is what lets
            // PHPStan treat $typeName as class-string below.
            if (!class_exists($typeName)) {
                throw new InvalidArgumentException(
                    "Closure parameters must be type-hinted with a non-builtin Options class"
                );
            }
            // @codeCoverageIgnoreEnd

            $args[] = $this->container->get($typeName);
        }

        $config(...$args);
    }
}
