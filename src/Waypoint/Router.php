<?php

namespace Waypoint;

use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use RuntimeException;

use Waypoint\Container;
use Waypoint\Csrf;
use Waypoint\Enums\{Message, RouteType};
use Waypoint\Http\{PublicFileServer, Request, Response, ResultRenderer, View};
use Waypoint\UI\WaypointController;
use Waypoint\Validator;
use Waypoint\Exceptions\{ForbiddenException, ValidationException};
use Waypoint\FileSystem;
use Waypoint\Options\{FileSystemOptions, RendererOptions};
use Waypoint\ViewAssets;
use Waypoint\Attributes\Inject;

class Router
{
    /**
     * httpMethod => path => plan. Plan kept as plain `mixed`, not RoutePlan:
     * importPlans() loads this from a `require`d routes.php cache file
     * PHPStan has no way to trust the shape of (same "load fast, don't
     * deep-validate" trust boundary as $tasks below and as
     * FileSystemOptions::$cacheValidate itself), so every real consumer
     * (dispatch(), matchRoute(), ...) narrows defensively instead of
     * assuming a RoutePlan.
     *
     * @var array<string, array<string, mixed>>
     */
    public array $staticRoutes = [];
    /**
     * httpMethod => list of plans. Same "plan is mixed" reasoning as
     * $staticRoutes above.
     *
     * @var array<string, array<int, mixed>>
     */
    public array $dynamicRoutes = [];
    /**
     * fullName => plan. Value kept as `mixed`, not `array<string, mixed>`
     * like the plan shape elsewhere: $tasks is public, and
     * RouterInternalsTest deliberately assigns a non-array entry directly
     * to prove executeTask() tolerates a corrupted one gracefully -- a
     * stricter type here would make that already-tested tolerance
     * unreachable by PHPStan's own reasoning.
     *
     * @var array<string, mixed>
     */
    public array $tasks = [];

    /**
     * {filename => mime} -- GET {assetsPath}/{filename} resolves through this, a strict map hit or a 404; the actual bytes are read from FileSystem::getAssetsDirectory() on demand, never held here.
     * @var array<string, string>
     */
    private array $viewAssetFiles = [];
    /**
     * {viewName => {css: ?filename, js: ?filename}} -- looked up by name when rendering a View, to emit its asset headers.
     * @var array<string, array{css: ?string, js: ?string}>
     */
    private array $viewAssetsByName = [];

    private ?Container $container = null;

    private FileSystemOptions $fileSystemOptions;
    private FileSystem $fileSystem;
    private RouteCompiler $routeCompiler;
    private ResultRenderer $resultRenderer;
    private PublicFileServer $publicFileServer;

    // -- Route Management --

    /**
     * @param RoutePlan|TaskPlan $plan
     */
    public function addCompiledRoute(array $plan, RouteType $type = RouteType::Unset): void
    {
        if ($type === RouteType::Unset) {
            throw new RuntimeException('addCompiledRoute() requires an explicit RouteType (Static, Dynamic, or Task).');
        }

        if ($type === RouteType::Task) {
            // isset() on 'fullName' -- a TaskPlan-only key -- is also what
            // lets PHPStan narrow $plan from RoutePlan|TaskPlan to TaskPlan
            // below, not just a runtime guard. RouteCompiler::compile()
            // (the only real producer of a Task plan) always sets it, so
            // this never actually fires.
            if (!isset($plan['fullName'])) {
                // @codeCoverageIgnoreStart
                throw new RuntimeException("addCompiledRoute(): a Task plan requires a 'fullName' key.");
                // @codeCoverageIgnoreEnd
            }
            $this->tasks[$plan['fullName']] = $plan;
            return;
        }

        // Same isset()-as-narrowing trick as above, this time on
        // 'httpMethod' (a RoutePlan-only key) to resolve $plan to RoutePlan
        // for both the Dynamic and Static arms below. Same "never actually
        // fires" reasoning as the Task branch above.
        if (!isset($plan['httpMethod'])) {
            // @codeCoverageIgnoreStart
            throw new RuntimeException("addCompiledRoute(): a route plan requires an 'httpMethod' key.");
            // @codeCoverageIgnoreEnd
        }

        if ($type === RouteType::Dynamic) {
            $this->dynamicRoutes[$plan['httpMethod']][] = $plan;
        } else {
            $this->staticRoutes[$plan['httpMethod']][$plan['path']] = $plan;
        }
    }

    /**
     * @return array{
     *     staticRoutes: array<string, array<string, mixed>>,
     *     dynamicRoutes: array<string, array<int, mixed>>,
     *     tasks: array<string, mixed>,
     *     viewAssetFiles: array<string, string>,
     *     viewAssetsByName: array<string, array{css: ?string, js: ?string}>
     * }
     */
    public function exportPlans(): array
    {
        return [
            'staticRoutes' => $this->staticRoutes,
            'dynamicRoutes' => $this->dynamicRoutes,
            'tasks' => $this->tasks,
            'viewAssetFiles' => $this->viewAssetFiles,
            'viewAssetsByName' => $this->viewAssetsByName,
        ];
    }

    /**
     * $data comes from a `require`d routes.php cache file -- PHPStan (and
     * PHP itself) has no static guarantee of its shape, so every field is
     * narrowed defensively rather than trusted outright; a key that's
     * missing or the wrong top-level type just falls back to empty instead
     * of crashing.
     *
     * @param array<string, mixed> $data
     */
    public function importPlans(array $data): void
    {
        $this->staticRoutes = $this->toMethodPathPlanMap($data['staticRoutes'] ?? null);
        $this->dynamicRoutes = $this->toMethodListPlanMap($data['dynamicRoutes'] ?? null);
        $this->tasks = $this->toStringKeyedArray($data['tasks'] ?? null);
        $this->viewAssetFiles = $this->toStringMap($data['viewAssetFiles'] ?? null);
        $this->viewAssetsByName = $this->toViewAssetsByNameMap($data['viewAssetsByName'] ?? null);
    }

    /**
     * Every real caller passes an already-array value pulled straight out
     * of a self-consistent cache file (or a well-formed test payload);
     * the is_array() guard below only protects against a hand-corrupted
     * cache, never exercised in practice.
     *
     * @return array<string, mixed>
     */
    private function toStringKeyedArray(mixed $value): array
    {
        if (!is_array($value)) {
            // @codeCoverageIgnoreStart
            return [];
            // @codeCoverageIgnoreEnd
        }
        $result = [];
        foreach ($value as $key => $item) {
            if (is_string($key)) {
                $result[$key] = $item;
            }
        }
        return $result;
    }

    /**
     * Same reasoning as toStringKeyedArray() above.
     * @return array<string, string>
     */
    private function toStringMap(mixed $value): array
    {
        if (!is_array($value)) {
            // @codeCoverageIgnoreStart
            return [];
            // @codeCoverageIgnoreEnd
        }
        $result = [];
        foreach ($value as $key => $item) {
            if (is_string($key) && is_string($item)) {
                $result[$key] = $item;
            }
        }
        return $result;
    }

    /**
     * Same reasoning as toStringKeyedArray() above.
     * @return array<string, array{css: ?string, js: ?string}>
     */
    private function toViewAssetsByNameMap(mixed $value): array
    {
        if (!is_array($value)) {
            // @codeCoverageIgnoreStart
            return [];
            // @codeCoverageIgnoreEnd
        }
        $result = [];
        foreach ($value as $key => $item) {
            if (!is_string($key) || !is_array($item)) {
                // @codeCoverageIgnoreStart
                continue;
                // @codeCoverageIgnoreEnd
            }
            $css = $item['css'] ?? null;
            $js = $item['js'] ?? null;
            $result[$key] = [
                'css' => is_string($css) ? $css : null,
                'js' => is_string($js) ? $js : null,
            ];
        }
        return $result;
    }

    /**
     * Same reasoning as toStringKeyedArray() above.
     * @return array<string, array<string, mixed>>
     */
    private function toMethodPathPlanMap(mixed $value): array
    {
        if (!is_array($value)) {
            // @codeCoverageIgnoreStart
            return [];
            // @codeCoverageIgnoreEnd
        }
        $result = [];
        foreach ($value as $method => $byPath) {
            if (!is_string($method) || !is_array($byPath)) {
                // @codeCoverageIgnoreStart
                continue;
                // @codeCoverageIgnoreEnd
            }
            $paths = [];
            foreach ($byPath as $path => $plan) {
                if (is_string($path)) {
                    $paths[$path] = $plan;
                }
            }
            $result[$method] = $paths;
        }
        return $result;
    }

    /**
     * Same reasoning as toStringKeyedArray() above.
     * @return array<string, array<int, mixed>>
     */
    private function toMethodListPlanMap(mixed $value): array
    {
        if (!is_array($value)) {
            // @codeCoverageIgnoreStart
            return [];
            // @codeCoverageIgnoreEnd
        }
        $result = [];
        foreach ($value as $method => $list) {
            if (!is_string($method) || !is_array($list)) {
                // @codeCoverageIgnoreStart
                continue;
                // @codeCoverageIgnoreEnd
            }
            $result[$method] = array_values($list);
        }
        return $result;
    }

    /**
     * Narrows an arbitrary value (an argPlan/propInject/middlewares list
     * pulled off a plan that's `mixed` at the property level -- see
     * $staticRoutes' docblock) to a list of arrays, dropping any entry
     * that isn't itself an array. Every real plan (compiled or loaded
     * from a self-consistent cache) already satisfies this, so the
     * is_array() guards here are defensive only.
     *
     * @return array<int, array<string, mixed>>
     */
    private function toListOfArrays(mixed $value): array
    {
        if (!is_array($value)) {
            // @codeCoverageIgnoreStart
            return [];
            // @codeCoverageIgnoreEnd
        }
        $result = [];
        foreach ($value as $item) {
            if (is_array($item)) {
                $result[] = $this->toStringKeyedArray($item);
            }
        }
        return $result;
    }

    /**
     * Same reasoning as toListOfArrays() above, but for a route/task's
     * formatter entry specifically.
     *
     * @return array{type?: string, options?: array<string, mixed>|null}
     */
    private function toFormatterSpec(mixed $value): array
    {
        if (!is_array($value)) {
            // @codeCoverageIgnoreStart
            return ['type' => 'json', 'options' => null];
            // @codeCoverageIgnoreEnd
        }
        $type = $value['type'] ?? 'json';
        $rawOptions = $value['options'] ?? null;
        return [
            'type' => is_string($type) ? $type : 'json',
            'options' => is_array($rawOptions) ? $this->toStringKeyedArray($rawOptions) : null,
        ];
    }

    /** The configured views directory, or null if this Router has no container to resolve RendererOptions through. */
    private function resolveViewsDirectory(): ?string
    {
        if (!$this->container) {
            return null;
        }
        return $this->container->get(RendererOptions::class)->directory;
    }

    // -- Public Entry Point: Boot --

    /**
     * @param class-string[] $controllers
     * @param class-string[] $serviceClasses
     * @param Container|null $container
     * @param array<string, mixed>|null $preloadedCache Route data the caller already read
     *  from the cache (e.g. App::attach(), which needs 'services' out of
     *  the same file anyway in trust mode) -- lets the Router use it
     *  directly instead of `require`-ing routes.php a second time.
     * @param FileSystemOptions|null $fileSystemOptions The configured
     *  FileSystemOptions instance to use (e.g. from
     *  App::configure(FileSystemOptions)). Falls back to resolving one from
     *  $container, then to a fresh default instance, so a Router built
     *  without either still works (just with caching off).
     */
    public function __construct(
        array $controllers,
        array $serviceClasses = [],
        ?Container $container = null,
        ?array $preloadedCache = null,
        ?FileSystemOptions $fileSystemOptions = null
    ) {
        if ($container) {
            $this->container = $container;#$router->setContainer($container);
        }

        $this->fileSystemOptions = $fileSystemOptions ?? $this->container?->get(FileSystemOptions::class) ?? new FileSystemOptions();
        $this->fileSystem = new FileSystem($this->fileSystemOptions);
        $this->routeCompiler = new RouteCompiler();
        $this->resultRenderer = new ResultRenderer();
        $this->publicFileServer = new PublicFileServer($this->fileSystemOptions);

        if ($preloadedCache !== null) {
            $this->importPlans($preloadedCache);
            return;
        }

        // Trust mode ($fileSystemOptions->cacheValidate = false): skip
        // isAvailable()'s reflect-and-compare pass entirely and just load
        // whatever is cached -- one stat call instead of a ReflectionClass
        // plus file_exists()/filemtime() per controller, every request.
        if ($this->fileSystemOptions->cacheDirectory !== null && !$this->fileSystemOptions->cacheValidate && $this->fileSystem->hasCachedRoutes()) {
            $this->fileSystem->loadToRouter($this);
            return;
        }

        $viewsDir = $this->resolveViewsDirectory();
        // Cheap (glob + filemtime only, no hashing) -- just enough to let
        // isAvailable() notice a changed view .css/.js and decide a rebuild
        // is needed, the same way it already notices a changed controller.
        $viewAssetMeta = $viewsDir !== null ? ViewAssets::discoverMeta($viewsDir) : [];

        if (!$this->fileSystem->isAvailable($controllers, $viewAssetMeta)) {
            $plans = $this->routeCompiler->compile($controllers);
            foreach ($plans['staticRoutes'] as $method => $routes) {
                foreach ($routes as $plan) {
                    $this->addCompiledRoute($plan, RouteType::Static);
                }
            }
            foreach ($plans['dynamicRoutes'] as $method => $routes) {
                foreach ($routes as $plan) {
                    $this->addCompiledRoute($plan, RouteType::Dynamic);
                }
            }
            foreach ($plans['tasks'] as $plan) {
                $this->addCompiledRoute($plan, RouteType::Task);
            }

            $compiledAssets = $viewsDir !== null ? ViewAssets::compile($viewsDir) : ['views' => [], 'files' => [], 'meta' => []];
            $this->viewAssetsByName = $compiledAssets['views'];
            // Writes each file's content to disk (getAssetsDirectory()) and
            // keeps only {filename => mime} here -- so routes.php never
            // embeds a single byte of any view's actual CSS/JS.
            $this->viewAssetFiles = $this->fileSystem->storeViewAssetFiles($compiledAssets['files']);

            $this->fileSystem->storeFromRouter($this, $controllers, $serviceClasses, $compiledAssets['meta']);
        } else {
            $this->fileSystem->loadToRouter($this);
        }
    }

    // -- Main Dispatch Method --

    public function dispatch(string $uri, string $httpMethod, Request $req, Response $res): void
    {
        $path = $this->normalizePath($uri);

        if ($this->publicFileServer->serve($path, $res)) {
            return;
        }

        if ($this->tryServeViewAsset($path, $res)) {
            return;
        }

        [$route, $params] = $this->matchRoute($httpMethod, $path);

        if (!$route) {
            $this->respondNotFound($res);
            return;
        }

        // Missing 'gzip' key (a route compiled/cached before #[NoGzip]
        // existed) defaults to compression allowed, not disabled -- so an
        // old cached routes.php keeps behaving exactly as before until
        // it's next rebuilt.
        if (!($route['gzip'] ?? true)) {
            $res->disableGzip();
        }

        // Both computed once at compile time (RouteCompiler) from native
        // #[\Deprecated] and #[Sunset] -- ?? false/empty() default to "not
        // set" the same defensive way as 'gzip' above, for a route
        // compiled/cached before either existed.
        if ($route['deprecated'] ?? false) {
            $res->withHeader('Deprecation', 'true');
        }
        $sunsetHeader = $route['sunsetHeader'] ?? null;
        if (is_string($sunsetHeader) && $sunsetHeader !== '') {
            $res->withHeader('Sunset', $sunsetHeader);
        }

        // State-changing methods only (GET/HEAD never carry a body/side
        // effect worth protecting); #[SkipCsrf] on the route opts out
        // entirely, e.g. a token-auth-only JSON API with no HTML forms.
        // Missing 'skipCsrf' key (a route compiled/cached before
        // #[SkipCsrf] existed) defaults to checking, not skipping --
        // unlike 'gzip'/'deprecated' above, the safe default for a
        // security check is "on" for a plan that predates it, not "off".
        if (
            in_array(strtoupper($httpMethod), ['POST', 'PUT', 'PATCH', 'DELETE'], true)
            && !($route['skipCsrf'] ?? false)
            && $this->container
            && !$this->container->get(Csrf::class)->verify($req)
        ) {
            throw new ForbiddenException(Message::CsrfTokenInvalid->value);
        }

        $controllerRef = $route['controller'] ?? null;
        // Every real compiled route's 'controller' names a real, already-
        // verified-to-exist class (see RouteCompiler::compileController());
        // this only guards a hand-corrupted cache.
        if (!is_string($controllerRef) || !class_exists($controllerRef)) {
            // @codeCoverageIgnoreStart
            $this->respondNotFound($res);
            return;
            // @codeCoverageIgnoreEnd
        }
        $controller = $this->resolveController($controllerRef);
        $this->injectControllerProperties($controller, $this->toListOfArrays($route['propInject'] ?? []), $req, $res);

        $handler = $this->buildRouteHandler($route, $controller, $params);
        $handler($req, $res);
    }

    /**
     * Wraps the final "build args -> call handler -> render" step with any
     * #[Middleware] attached to the controller class and/or the route method,
     * innermost (last declared) first. Class-level middleware always precedes
     * method-level middleware in $route['middlewares'] (see compileController()),
     * so it runs first. Any exception thrown by a middleware or the handler
     * itself propagates to the caller (App::handle), which maps it to a
     * response via its exception handler registry.
     *
     * @param array<string, mixed> $route
     * @param array<string, string> $params
     */
    private function buildRouteHandler(array $route, object $controller, array $params): callable
    {
        $final = function (Request $req, Response $res) use ($route, $controller, $params): void {
            $args = $this->buildMethodArguments($this->toListOfArrays($route['argPlan'] ?? []), $req, $res, $params);
            $method = $route['method'] ?? null;
            // Every real compiled route always has a 'method' (see
            // RouteCompiler::compileController()); this only guards a
            // hand-corrupted cache.
            if (!is_string($method)) {
                // @codeCoverageIgnoreStart
                throw new RuntimeException('buildRouteHandler(): route plan is missing a string \'method\'.');
                // @codeCoverageIgnoreEnd
            }
            $result = $controller->{$method}(...$args);
            $this->renderResult($result, $req, $res, $this->toFormatterSpec($route['formatter'] ?? null));
        };

        return array_reduce(
            array_reverse($this->toListOfArrays($route['middlewares'] ?? [])),
            function (callable $next, array $mw): callable {
                return function (Request $req, Response $res) use ($mw, $next) {
                    $class = $mw['class'] ?? null;
                    $method = $mw['method'] ?? null;
                    // Every real compiled #[Middleware] entry always has a
                    // valid class/method (see RouteCompiler::collectMiddlewares());
                    // this only guards a hand-corrupted cache.
                    if (!is_string($class) || !class_exists($class) || !is_string($method)) {
                        // @codeCoverageIgnoreStart
                        return $next($req, $res);
                        // @codeCoverageIgnoreEnd
                    }
                    $middleware = $this->resolveController($class);
                    $this->injectControllerProperties($middleware, $this->toListOfArrays($mw['propInject'] ?? []), $req, $res);
                    return $middleware->{$method}($req, $res, $next);
                };
            },
            $final
        );
    }

    // -- Private Helpers for Dispatch --

    private function normalizePath(string $uri): string
    {
        return '/' . ltrim(rtrim($uri, '/'), '/');
    }

    /**
     * Serves a view's cache-busted CSS/JS through GET {assetsPath}/{filename}
     * (assetsPath configured via FileSystemOptions::$assetsPath, default
     * '/assets'), resolving strictly against the compiled {filename =>
     * mime} map -- never by constructing a path from the request's own
     * filename, so an unrecognized filename is a 404, not a traversal
     * attempt; $filename is only ever used to read
     * FileSystem::getAssetsDirectory()/{filename} once it's already
     * confirmed to be a known key, never built from unvalidated request
     * input directly. The URL is content-hashed, so a hit can be cached by
     * the browser forever.
     */
    private function tryServeViewAsset(string $path, Response $res): bool
    {
        $prefix = $this->fileSystemOptions->assetsPath . '/';
        if (!str_starts_with($path, $prefix)) {
            return false;
        }

        $filename = substr($path, strlen($prefix));
        $mime = $this->viewAssetFiles[$filename] ?? null;

        if ($mime === null) {
            return false;
        }

        $content = $this->fileSystem->readViewAssetFile($filename);
        if ($content === null) {
            return false;
        }

        $res->withHeader('Content-Type', $mime)
            ->withHeader('Cache-Control', 'public, max-age=31536000, immutable')
            ->withHeader('Content-Length', (string) strlen($content))
            ->write($content);

        return true;
    }

    /** @return array{0: array<string, mixed>|null, 1: array<string, string>} */
    private function matchRoute(string $httpMethod, string $path): array
    {
        $m = strtoupper($httpMethod);
        $params = [];
        $route = $this->staticRoutes[$m][$path] ?? null;

        // A plan is `mixed` at the property level (see $staticRoutes'
        // docblock) -- is_array() both guards a corrupted cache entry and
        // (via toStringKeyedArray()) narrows it to array<string, mixed>
        // for the return below.
        if (is_array($route)) {
            return [$this->toStringKeyedArray($route), $params];
        }

        foreach ($this->dynamicRoutes[$m] ?? [] as $rawEntry) {
            // Every real compiled dynamic route entry is a well-formed
            // RoutePlan array with a string 'regex' (see
            // RouteCompiler::compileController()); these two guards only
            // protect against a hand-corrupted cache.
            if (!is_array($rawEntry)) {
                // @codeCoverageIgnoreStart
                continue;
                // @codeCoverageIgnoreEnd
            }
            $entry = $this->toStringKeyedArray($rawEntry);
            $regex = $entry['regex'] ?? null;
            if (!is_string($regex)) {
                // @codeCoverageIgnoreStart
                continue;
                // @codeCoverageIgnoreEnd
            }
            if (preg_match($regex, $path, $matches)) {
                foreach ($matches as $key => $value) {
                    if (is_string($key)) {
                        $params[$key] = $value;
                    }
                }
                return [$entry, $params];
            }
        }

        return [null, []];
    }

    private function respondNotFound(Response $res): void
    {
        $res->status(404)
            ->withHeader('Content-Type', 'application/json')
            // A hardcoded literal array always encodes successfully;
            // json_encode() is just typed to allow failure in general.
            ->write(json_encode(['error' => 'Not found']) ?: '{"error":"Not found"}');
    }

    /** @param class-string $class */
    private function resolveController(string $class): object
    {
        return $this->container ? $this->container->get($class) : new $class();
    }

    /**
     * Looser than the plan-compile-time PropInjectEntry shape on purpose:
     * a route/middleware plan can come from an untrusted cache load
     * (Router::$staticRoutes/$dynamicRoutes are `mixed`-valued -- see
     * their docblocks), so every entry is narrowed defensively instead of
     * assumed well-shaped, the same as injectTaskProperties() below.
     *
     * @param array<int, array<string, mixed>> $propInject
     */
    private function injectControllerProperties(object $controller, array $propInject, Request $req, Response $res): void
    {
        foreach ($propInject as $p) {
            $propName = $p['name'] ?? null;
            $type = $p['type'] ?? null;
            // Every real compiled #[Inject] property always has a string
            // 'name' (see RouteCompiler::collectPropertyInjections()); this
            // only guards a hand-corrupted cache.
            if (!is_string($propName)) {
                // @codeCoverageIgnoreStart
                continue;
                // @codeCoverageIgnoreEnd
            }
            $refProp = new ReflectionProperty(get_class($controller), $propName);

            switch ($type) {
                case Request::class:
                    $refProp->setValue($controller, $req);
                    break;
                case Response::class:
                    $refProp->setValue($controller, $res);
                    break;
                case self::class:
                    $refProp->setValue($controller, $this);
                    break;
                default:
                    if ($this->container && is_string($type) && class_exists($type)) {
                        $refProp->setValue($controller, $this->container->get($type));
                    }
            }
        }
    }

    /**
     * Wires up #[Inject] properties on a View (or subclass) returned from
     * a route handler -- e.g. #[Inject] Environment $env; on View itself,
     * readable as $this->env from inside a view/layout template.
     *
     * Unlike a controller/#[Middleware] class, a View is a plain value
     * object user code constructs directly with `new`, never through the
     * container, and its concrete class isn't known until the handler
     * actually returns one -- so this can't be precomputed into the route
     * plan the way collectPropertyInjections() is; it's a lightweight
     * runtime reflection pass instead, done once per View actually
     * rendered, and only when a container is available to resolve from.
     */
    private function injectViewProperties(View $view): void
    {
        if (!$this->container) {
            return;
        }

        foreach ((new ReflectionClass($view))->getProperties() as $prop) {
            if (!$prop->getAttributes(Inject::class)) {
                continue;
            }
            $propType = $prop->getType();
            $type = $propType instanceof ReflectionNamedType ? $propType->getName() : null;
            if (!$type || !class_exists($type) || !$this->container->has($type)) {
                continue;
            }
            $prop->setValue($view, $this->container->get($type));
        }
    }

    /**
     * @param array<int, array<string, mixed>> $argPlan
     * @param array<string, string> $params
     * @return array<int, mixed>
     */
    private function buildMethodArguments(array $argPlan, Request $req, Response $res, array $params): array
    {
        $args = [];
        foreach ($argPlan as $arg) {
            $name = $arg['name'] ?? null;
            $name = is_string($name) ? $name : '';
            $class = $arg['class'] ?? null;

            switch ($arg['inject']) {
                case 'Request':
                    $args[] = $req;
                    break;
                case 'Response':
                    $args[] = $res;
                    break;
                case 'Route':
                    $args[] = $params[$name] ?? null;
                    break;
                case 'Query':
                    $args[] = $req->query($name);
                    break;
                case 'Body':
                    // RouteCompiler::buildArgPlan() only ever sets a
                    // 'Body' argPlan entry's 'class' after its own
                    // class_exists() check, so this never actually fires.
                    if (!is_string($class) || !class_exists($class)) {
                        // @codeCoverageIgnoreStart
                        $args[] = null;
                        break;
                        // @codeCoverageIgnoreEnd
                    }
                    $dto = new $class($req->body());
                    if ($arg['validate'] ?? false) {
                        $validator = new Validator();
                        $errors = $validator->validate($dto);
                        if ($errors) {
                            throw new ValidationException("Validation failed", $errors);
                        }
                    }
                    $args[] = $dto;
                    break;
                case 'BodyCollection':
                    // Same reasoning as the 'Body' case above -- never
                    // actually fires.
                    if (!is_string($class) || !class_exists($class)) {
                        // @codeCoverageIgnoreStart
                        $args[] = [];
                        break;
                        // @codeCoverageIgnoreEnd
                    }
                    // Request::$body is declared `array` (see Request::capture()),
                    // so it can never actually be anything else here -- no
                    // "not an array" branch is reachable to guard against.
                    $items = $req->body();
                    $validator = ($arg['validate'] ?? false) ? new Validator() : null;
                    $collection = [];
                    $errors = [];
                    foreach ($items as $index => $item) {
                        $dto = new $class($item);
                        if ($validator) {
                            $itemErrors = $validator->validate($dto);
                            if ($itemErrors) {
                                $errors["$index"] = $itemErrors;
                            }
                        }
                        $collection[] = $dto;
                    }
                    if ($errors) {
                        throw new ValidationException("Validation failed", $errors);
                    }
                    $args[] = $collection;
                    break;
                case 'Scalar':
                    $val = $params[$name] ?? $req->query($name) ?? null;
                    $scalarType = $arg['type'] ?? null;
                    if (is_string($scalarType)) {
                        settype($val, $scalarType);
                    }
                    $args[] = $val;
                    break;
                default:
                    $args[] = null;
                    break;
            }
        }
        return $args;
    }

    /**
     * Dispatches a route handler's return value to a response: a View gets
     * rendered here directly (renderView()), since that needs this
     * Router's own compiled CSS/JS asset lookups and #[Inject] wiring;
     * everything else (JSON/file/XML) is delegated to ResultRenderer, which
     * needs none of that.
     *
     * @param array{type?: string, options?: array<string, mixed>|null} $formatter
     */
    private function renderResult(mixed $result, Request $req, Response $res, array $formatter): void
    {
        if ($result instanceof View) {
            $this->renderView($result, $req, $res);
            return;
        }

        $this->resultRenderer->render($result, $res, $formatter);
    }

    private function renderView(View $view, Request $req, Response $res): void
    {
        // Resolved through this Router's own container (not
        // Waypoint::getConfig()) so it's the exact same Csrf instance
        // injectViewProperties() hands to $view->csrf below -- issueFor()'s
        // resolved token has to already be sitting on that instance by the
        // time the view's #[Inject] properties are wired up.
        if ($this->container) {
            $this->container->get(Csrf::class)->issueFor($req, $res);
        }

        $this->injectViewProperties($view);
        $view->setAssetsPath($this->fileSystemOptions->assetsPath);
        $view->setWaypointJsPath($this->resolveWaypointJsPath());

        $assets = $this->getViewAssets($view->getViewName());
        // Always set, even when both are null: a full (non-partial)
        // render's layout can call $view->assetTags()/scopeAttribute()
        // itself, since it has no client-side JS running yet to read
        // the equivalent response headers below the way a partial-swap
        // navigation does.
        $view->setAssets($assets);

        if ($assets['css'] !== null || $assets['js'] !== null) {
            // The view's own name too, not just its asset filenames --
            // a client applying these needs it to set data-view on the
            // swapped container, which is what ViewAssets::scopeCss()'s
            // [data-view="..."] selectors actually match against.
            $res->withHeader('X-Waypoint-View-Name', $view->getViewName());
            if ($assets['css'] !== null) {
                $res->withHeader('X-Waypoint-View-Css', $assets['css']);
            }
            if ($assets['js'] !== null) {
                $res->withHeader('X-Waypoint-View-Js', $assets['js']);
            }
        }

        $res->withHeader('Content-Type', 'text/html')
            ->write($view->render());
    }

    /**
     * The compiled URL path for WaypointController's own route, or null
     * if that controller was never attached -- View::waypointJsTag() uses
     * this to render nothing at all rather than a <script> tag pointing at
     * a route that doesn't exist. A plain scan over the already-compiled
     * static GET routes (WaypointController's #[Get('waypoint.js')] is
     * never dynamic), not a reflection pass -- cheap enough to just redo
     * per render rather than caching.
     */
    private function resolveWaypointJsPath(): ?string
    {
        foreach ($this->staticRoutes['GET'] ?? [] as $path => $plan) {
            if (is_array($plan) && ($plan['controller'] ?? null) === WaypointController::class) {
                return $path;
            }
        }
        return null;
    }

    /**
     * The compiled {css, js} cache-busted filenames for the given
     * view/layout template basename (a '*.php' file's name without the
     * extension, in the configured RendererOptions directory), or
     * {css: null, js: null} if it has none. Public so View can look up its
     * *layout's* own assets (View::$layoutAssets) the same way
     * renderView() already looks up the view's own -- ViewAssets::compile()
     * treats every '.php' file in the views directory identically, whether
     * it's ever used as a plain view or as a layout, so this one lookup
     * serves both.
     *
     * @return array{css: ?string, js: ?string}
     */
    public function getViewAssets(string $name): array
    {
        return $this->viewAssetsByName[$name] ?? ['css' => null, 'js' => null];
    }

    /**
     * 'version'/'unversionedPath' default to null/$path for a route
     * compiled/cached before #[Version] existed -- same defensive fallback
     * as 'gzip' elsewhere, and exactly what an always-unversioned route
     * looks like anyway (version null, unversionedPath === its own path).
     * OpenAPIGenerator is the one real consumer of both: grouping/deduping
     * routes across versions for the combined spec.json (see
     * OpenAPIGenerator::selectEligibleRoutes()) needs the *same* version
     * resolution RouteCompiler already did once at compile time, rather
     * than a second, potentially-diverging derivation from raw attributes.
     *
     * @return array<int, RouteSummary>
     */
    public function getRoutes(): array
    {
        $routes = [];
        // Every real compiled route (static or dynamic) is a well-formed
        // RoutePlan array with a string 'path' (see RouteCompiler); these
        // guards only protect against a hand-corrupted cache.
        foreach ($this->staticRoutes as $method => $byPath) {
            foreach ($byPath as $path => $rawPlan) {
                if (!is_array($rawPlan)) {
                    // @codeCoverageIgnoreStart
                    continue;
                    // @codeCoverageIgnoreEnd
                }
                $routes[] = $this->toRouteSummary($method, $path, $this->toStringKeyedArray($rawPlan));
            }
        }
        foreach ($this->dynamicRoutes as $method => $plans) {
            foreach ($plans as $rawPlan) {
                if (!is_array($rawPlan)) {
                    // @codeCoverageIgnoreStart
                    continue;
                    // @codeCoverageIgnoreEnd
                }
                $plan = $this->toStringKeyedArray($rawPlan);
                $rawPath = $plan['path'] ?? null;
                if (!is_string($rawPath)) {
                    // @codeCoverageIgnoreStart
                    continue;
                    // @codeCoverageIgnoreEnd
                }
                $routes[] = $this->toRouteSummary($method, $rawPath, $plan);
            }
        }
        return $routes;
    }

    /**
     * @param array<string, mixed> $plan
     * @return RouteSummary
     */
    private function toRouteSummary(string $method, string $rawPath, array $plan): object
    {
        $controller = $plan['controller'] ?? null;
        $controllerRef = (is_string($controller) || is_object($controller)) ? $controller : '';
        $handlerMethod = $plan['method'] ?? null;
        $version = $plan['version'] ?? null;
        $unversionedPath = $plan['unversionedPath'] ?? null;

        return (object) [
            'method' => $method,
            'rawPath' => $rawPath,
            'handlerSpec' => [$controllerRef, is_string($handlerMethod) ? $handlerMethod : ''],
            'version' => is_string($version) ? $version : null,
            'unversionedPath' => is_string($unversionedPath) ? $unversionedPath : $rawPath,
        ];
    }

    /** @param array<int, RouteSummary> $routes */
    public function findRoute(array $routes, string $method, string $path): ?object
    {
        foreach ($routes as $route) {
            if ($route->rawPath === $path && $route->method === $method) {
                return $route;
            }
        }
        return null;
    }

    /** @param string[] $argv */
    public function executeTask(string $name, array $argv = [], int $argc = 0): mixed
    {
        // 1) Try exact key first (full name, e.g. "prefix:task" or plain "task")
        $rawPlan = $this->tasks[$name] ?? null;

        // 2) Fallback: scan for a unique match by fullName/name or suffix ":name"
        if (!$rawPlan) {
            $matches = [];
            foreach ($this->tasks as $fullName => $p) {
                if (!is_array($p)) {
                    continue;
                }
                $entry = $this->toStringKeyedArray($p);
                $short = $entry['name'] ?? null;
                $full = $entry['fullName'] ?? null;
                $fullMatches = is_string($full) && ($full === $name || str_ends_with($full, ':' . $name));

                if ($fullMatches || $short === $name) {
                    $matches[] = $entry;
                }
            }
            if (count($matches) === 1) {
                $rawPlan = $matches[0];
            } elseif (count($matches) > 1) {
                throw new RuntimeException("Ambiguous task name '{$name}'.");
            }
        }

        if (!is_array($rawPlan)) {
            throw new RuntimeException("Task '{$name}' not found.");
        }
        $plan = $this->toStringKeyedArray($rawPlan);

        // Resolve manager (container-aware). Every real compiled task
        // always has a valid 'manager' (see RouteCompiler::compileManager());
        // this only guards a hand-corrupted cache -- unlike the 'method'
        // check below, which RouterInternalsTest does exercise directly.
        $managerRef = $plan['manager'] ?? '';
        if (!is_string($managerRef) || !class_exists($managerRef)) {
            // @codeCoverageIgnoreStart
            throw new RuntimeException("Task handler for '{$name}' is invalid.");
            // @codeCoverageIgnoreEnd
        }
        $manager = $this->resolveController($managerRef);

        // Inject properties (DI) — skip Request/Response for tasks
        $this->injectTaskProperties($manager, $this->toListOfArrays($plan['propInject'] ?? []));

        // Call EXACTLY with ($argv, $argc)
        $method = $plan['method'] ?? null;
        if (!is_string($method) || !method_exists($manager, $method)) {
            throw new RuntimeException("Task handler for '{$name}' is invalid.");
        }

        return $manager->{$method}($argv, $argc);
    }

    /**
     * Inject DI for tasks, but never Request/Response (no HTTP in tasks).
     *
     * @param array<int, array<string, mixed>> $propInject Looser than
     *  injectControllerProperties()'s equivalent shape on purpose:
     *  RouterInternalsTest deliberately exercises a hand-crafted task plan
     *  with a null 'name' entry, to prove this stays a no-op instead of
     *  crashing.
     */
    private function injectTaskProperties(object $target, array $propInject): void
    {
        foreach ($propInject as $p) {
            $propName = $p['name'] ?? null;
            $type = $p['type'] ?? null;
            if (!is_string($propName) || $propName === '') {
                continue;
            }

            // Skip HTTP-bound injections for tasks
            if ($type === Request::class || $type === Response::class) {
                continue;
            }

            $refProp = new ReflectionProperty(get_class($target), $propName);

            switch ($type) {
                case self::class:
                    $refProp->setValue($target, $this);
                    break;

                default:
                    if ($this->container && is_string($type) && class_exists($type)) {
                        $refProp->setValue($target, $this->container->get($type));
                    }
            }
        }
    }
}
