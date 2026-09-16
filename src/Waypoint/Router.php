<?php

namespace Waypoint;

use RuntimeException;
use Waypoint\Attributes\Inject;
use Waypoint\Enums\RouteType;
use Waypoint\Http\{PublicFileServer, Redirect, Request, Response, ResultRenderer, View};
use Waypoint\Options\{FileSystemOptions, RendererOptions};
use Waypoint\Routing\{ArgumentResolver, PropertyInjector, RouteGuard};
use Waypoint\Support\Arr;
use Waypoint\UI\WaypointController;

/**
 * Matches a request against the compiled route plans (RouteCompiler, cached
 * by FileSystem) and runs the matched controller method: access checks,
 * per-route middleware, argument binding, and rendering of the result.
 * Also serves compiled view assets and runs CLI tasks.
 *
 * Plans are plain string-keyed arrays: they are loaded from a `require`d
 * cache file, so consumers read keys defensively (`?? default`) rather than
 * trusting a shape -- which also keeps a routes.php written by an older
 * Waypoint working until it is rebuilt.
 */
class Router
{
    /** @var array<string, array<string, array<string, mixed>>> httpMethod => path => plan */
    public array $staticRoutes = [];

    /** @var array<string, list<array<string, mixed>>> httpMethod => plans, matched by 'regex' in order */
    public array $dynamicRoutes = [];

    /** @var array<string, mixed> fullName => plan (mixed: tests deliberately plant malformed entries to prove executeTask() tolerates them) */
    public array $tasks = [];

    /** @var array<string, string> filename => mime; GET {assetsPath}/{filename} is a strict lookup here, never a path built from the request. */
    private array $viewAssetFiles = [];

    /** @var array<string, array{css: ?string, js: ?string}> */
    private array $viewAssetsByName = [];

    private ?Container $container;
    private FileSystemOptions $fileSystemOptions;
    private FileSystem $fileSystem;
    private ResultRenderer $resultRenderer;
    private PublicFileServer $publicFileServer;
    private PropertyInjector $injector;
    private RouteGuard $guard;

    /** Memoized resolveWaypointJsPath(); false = not resolved yet. */
    private string|null|false $waypointJsPath = false;

    /**
     * @param class-string[] $controllers
     * @param class-string[] $serviceClasses Persisted into the cache so trust mode can skip discovery.
     * @param array<string, mixed>|null $preloadedCache Already-read routes.php data (App::attach() in trust mode), so it isn't `require`d twice.
     * @param FileSystemOptions|null $fileSystemOptions Falls back to the container's, then to defaults (caching off).
     */
    public function __construct(
        array $controllers,
        array $serviceClasses = [],
        ?Container $container = null,
        ?array $preloadedCache = null,
        ?FileSystemOptions $fileSystemOptions = null
    ) {
        $this->container = $container;
        $this->fileSystemOptions = $fileSystemOptions ?? $container?->get(FileSystemOptions::class) ?? new FileSystemOptions();
        $this->fileSystem = new FileSystem($this->fileSystemOptions);
        $this->resultRenderer = new ResultRenderer();
        $this->publicFileServer = new PublicFileServer($this->fileSystemOptions);
        $this->injector = new PropertyInjector($container, $this);
        $this->guard = new RouteGuard($container);

        if ($preloadedCache !== null) {
            $this->importPlans($preloadedCache);
            return;
        }

        // Trust mode: one stat call, no per-controller reflection.
        $trusted = $this->fileSystemOptions->cacheDirectory !== null && !$this->fileSystemOptions->cacheValidate;
        if ($trusted && $this->fileSystem->hasCachedRoutes()) {
            $this->fileSystem->loadToRouter($this);
            return;
        }

        $viewsDir = $container?->get(RendererOptions::class)->directory;
        $viewAssetMeta = $viewsDir !== null ? ViewAssets::discoverMeta($viewsDir) : [];
        if ($this->fileSystem->isAvailable($controllers, $viewAssetMeta)) {
            $this->fileSystem->loadToRouter($this);
            return;
        }

        $this->compile($controllers, $serviceClasses, $viewsDir);
    }

    /**
     * @param class-string[] $controllers
     * @param class-string[] $serviceClasses
     */
    private function compile(array $controllers, array $serviceClasses, ?string $viewsDir): void
    {
        $plans = (new RouteCompiler())->compile($controllers);
        foreach ($plans['staticRoutes'] as $routes) {
            foreach ($routes as $plan) {
                $this->addCompiledRoute($plan, RouteType::Static);
            }
        }
        foreach ($plans['dynamicRoutes'] as $routes) {
            foreach ($routes as $plan) {
                $this->addCompiledRoute($plan, RouteType::Dynamic);
            }
        }
        foreach ($plans['tasks'] as $plan) {
            $this->addCompiledRoute($plan, RouteType::Task);
        }

        $assets = $viewsDir !== null ? ViewAssets::compile($viewsDir) : ['views' => [], 'files' => [], 'meta' => []];
        $this->viewAssetsByName = $assets['views'];
        // File contents go to disk; routes.php only keeps {filename => mime}.
        $this->viewAssetFiles = $this->fileSystem->storeViewAssetFiles($assets['files']);
        $this->fileSystem->storeFromRouter($this, $controllers, $serviceClasses, $assets['meta']);
    }

    // -- Plans --

    /** @param RoutePlan|TaskPlan $plan */
    public function addCompiledRoute(array $plan, RouteType $type = RouteType::Unset): void
    {
        $this->waypointJsPath = false;
        switch ($type) {
            case RouteType::Task:
                if (!isset($plan['fullName'])) {
                    // @codeCoverageIgnoreStart
                    // RouteCompiler always sets it; this also narrows $plan to TaskPlan.
                    throw new RuntimeException("addCompiledRoute(): a Task plan requires a 'fullName' key.");
                    // @codeCoverageIgnoreEnd
                }
                $this->tasks[$plan['fullName']] = $plan;
                return;
            case RouteType::Static:
            case RouteType::Dynamic:
                if (!isset($plan['httpMethod'])) {
                    // @codeCoverageIgnoreStart
                    // same narrowing trick, to RoutePlan.
                    throw new RuntimeException("addCompiledRoute(): a route plan requires an 'httpMethod' key.");
                    // @codeCoverageIgnoreEnd
                }
                if ($type === RouteType::Dynamic) {
                    $this->dynamicRoutes[$plan['httpMethod']][] = $plan;
                } else {
                    $this->staticRoutes[$plan['httpMethod']][$plan['path']] = $plan;
                }
                return;
            default:
                throw new RuntimeException('addCompiledRoute() requires an explicit RouteType (Static, Dynamic, or Task).');
        }
    }

    /**
     * @return array{
     *     staticRoutes: array<string, array<string, array<string, mixed>>>,
     *     dynamicRoutes: array<string, list<array<string, mixed>>>,
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
     * Loads exportPlans()' shape back from a `require`d cache file, narrowing every level and dropping anything malformed.
     * @param array<string, mixed> $data
     */
    public function importPlans(array $data): void
    {
        $this->waypointJsPath = false;
        $this->staticRoutes = [];
        foreach (Arr::stringKeyed($data['staticRoutes'] ?? null) as $method => $byPath) {
            foreach (Arr::stringKeyed($byPath) as $path => $plan) {
                if (is_array($plan)) {
                    $this->staticRoutes[$method][$path] = Arr::stringKeyed($plan);
                }
            }
        }
        $this->dynamicRoutes = [];
        foreach (Arr::stringKeyed($data['dynamicRoutes'] ?? null) as $method => $plans) {
            $this->dynamicRoutes[$method] = Arr::listOfStringKeyed($plans);
        }
        $this->tasks = Arr::stringKeyed($data['tasks'] ?? null);
        $this->viewAssetFiles = Arr::stringMap($data['viewAssetFiles'] ?? null);
        $this->viewAssetsByName = [];
        foreach (Arr::stringKeyed($data['viewAssetsByName'] ?? null) as $name => $entry) {
            $entry = Arr::stringKeyed($entry);
            $css = $entry['css'] ?? null;
            $js = $entry['js'] ?? null;
            $this->viewAssetsByName[$name] = ['css' => is_string($css) ? $css : null, 'js' => is_string($js) ? $js : null];
        }
    }

    // -- HTTP dispatch --

    public function dispatch(string $uri, string $httpMethod, Request $req, Response $res): void
    {
        $path = '/' . trim($uri, '/');

        if ($this->publicFileServer->serve($path, $res) || $this->tryServeViewAsset($path, $res)) {
            return;
        }

        [$route, $params] = $this->matchRoute($httpMethod, $path);
        if ($route === null) {
            $this->respondNotFound($res);
            return;
        }

        $this->applyRouteHeaders($route, $res);
        $this->guard->check($route, $httpMethod, $req);

        $controllerRef = $route['controller'] ?? null;
        if (!is_string($controllerRef) || !class_exists($controllerRef)) {
            // @codeCoverageIgnoreStart
            // only a hand-corrupted cache.
            $this->respondNotFound($res);
            return;
            // @codeCoverageIgnoreEnd
        }
        $controller = $this->resolve($controllerRef);
        $this->injector->injectPlanned($controller, $route['propInject'] ?? null, $req, $res);

        $this->buildRouteHandler($route, $controller, $params)($req, $res);
    }

    /**
     * Headers decided at compile time: #[NoGzip], #[\Deprecated], #[Sunset]. Missing keys (older cache) mean "not set".
     * @param array<string, mixed> $route
     */
    private function applyRouteHeaders(array $route, Response $res): void
    {
        if (!($route['gzip'] ?? true)) {
            $res->disableGzip();
        }
        if ($route['deprecated'] ?? false) {
            $res->withHeader('Deprecation', 'true');
        }
        $sunset = $route['sunsetHeader'] ?? null;
        if (is_string($sunset) && $sunset !== '') {
            $res->withHeader('Sunset', $sunset);
        }
    }

    /**
     * Wraps "bind args -> call -> render" in the route's #[Middleware] chain
     * (class-level first, as compiled). Exceptions propagate to App's handlers.
     *
     * @param array<string, mixed> $route
     * @param array<string, string> $params
     */
    private function buildRouteHandler(array $route, object $controller, array $params): callable
    {
        $final = function (Request $req, Response $res) use ($route, $controller, $params): void {
            $method = $route['method'] ?? null;
            if (!is_string($method)) {
                // @codeCoverageIgnoreStart
                // only a hand-corrupted cache.
                throw new RuntimeException('buildRouteHandler(): route plan is missing a string \'method\'.');
                // @codeCoverageIgnoreEnd
            }
            $args = ArgumentResolver::resolve(Arr::listOfStringKeyed($route['argPlan'] ?? null), $req, $res, $params);
            $this->renderResult($controller->{$method}(...$args), $req, $res, $route['formatter'] ?? null);
        };

        return array_reduce(
            array_reverse(Arr::listOfStringKeyed($route['middlewares'] ?? null)),
            fn(callable $next, array $mw): callable => function (Request $req, Response $res) use ($mw, $next): mixed {
                $class = $mw['class'] ?? null;
                $method = $mw['method'] ?? null;
                if (!is_string($class) || !class_exists($class) || !is_string($method)) {
                    // @codeCoverageIgnoreStart
                    // only a hand-corrupted cache.
                    return $next($req, $res);
                    // @codeCoverageIgnoreEnd
                }
                $middleware = $this->resolve($class);
                $this->injector->injectPlanned($middleware, $mw['propInject'] ?? null, $req, $res);
                return $middleware->{$method}($req, $res, $next);
            },
            $final
        );
    }

    /** @return array{0: array<string, mixed>|null, 1: array<string, string>} */
    private function matchRoute(string $httpMethod, string $path): array
    {
        $m = strtoupper($httpMethod);
        if (isset($this->staticRoutes[$m][$path])) {
            return [$this->staticRoutes[$m][$path], []];
        }
        foreach ($this->dynamicRoutes[$m] ?? [] as $plan) {
            $regex = $plan['regex'] ?? null;
            if (is_string($regex) && preg_match($regex, $path, $matches)) {
                $params = [];
                foreach ($matches as $key => $value) {
                    if (is_string($key)) {
                        $params[$key] = $value;
                    }
                }
                return [$plan, $params];
            }
        }
        return [null, []];
    }

    /** Content-hashed view CSS/JS under {assetsPath}/, immutable-cacheable; unknown filenames fall through to routing. */
    private function tryServeViewAsset(string $path, Response $res): bool
    {
        $prefix = $this->fileSystemOptions->assetsPath . '/';
        if (!str_starts_with($path, $prefix)) {
            return false;
        }
        $filename = substr($path, strlen($prefix));
        $mime = $this->viewAssetFiles[$filename] ?? null;
        $content = $mime !== null ? $this->fileSystem->readViewAssetFile($filename) : null;
        if ($mime === null || $content === null) {
            return false;
        }
        $res->withHeader('Content-Type', $mime)
            ->withHeader('Cache-Control', 'public, max-age=31536000, immutable')
            ->withHeader('Content-Length', (string) strlen($content))
            ->write($content);
        return true;
    }

    private function respondNotFound(Response $res): void
    {
        $res->status(404)->withHeader('Content-Type', 'application/json')->write('{"error":"Not found"}');
    }

    /** @param class-string $class */
    private function resolve(string $class): object
    {
        return $this->container !== null ? $this->container->get($class) : new $class();
    }

    // -- Results --

    /** A View renders here (it needs this Router's asset lookups and injection); a Redirect is just status + Location; anything else goes through ResultRenderer. */
    private function renderResult(mixed $result, Request $req, Response $res, mixed $formatter): void
    {
        if ($result instanceof View) {
            $this->renderView($result, $req, $res);
            return;
        }
        if ($result instanceof Redirect) {
            $res->status($result->status)->withHeader('Location', $result->location);
            return;
        }
        $spec = Arr::stringKeyed($formatter);
        $type = $spec['type'] ?? 'json';
        $options = $spec['options'] ?? null;
        $this->resultRenderer->render($result, $res, [
            'type' => is_string($type) ? $type : 'json',
            'options' => is_array($options) ? Arr::stringKeyed($options) : null,
        ]);
    }

    private function renderView(View $view, Request $req, Response $res): void
    {
        // Same Csrf instance the view's #[Inject] receives below, so its token is already resolved.
        $this->container?->get(Csrf::class)->issueFor($req, $res);

        $this->injector->injectReflected($view);
        $view->setAssetsPath($this->fileSystemOptions->assetsPath);
        $view->setWaypointJsPath($this->resolveWaypointJsPath());

        $assets = $this->getViewAssets($view->getViewName());
        $view->setAssets($assets); // the layout renders the tags itself on a full page load

        // A partial-swap client applies these instead: the name is what data-view/CSS scoping keys on.
        if ($assets['css'] !== null || $assets['js'] !== null) {
            $res->withHeader('X-Waypoint-View-Name', $view->getViewName());
            if ($assets['css'] !== null) {
                $res->withHeader('X-Waypoint-View-Css', $assets['css']);
            }
            if ($assets['js'] !== null) {
                $res->withHeader('X-Waypoint-View-Js', $assets['js']);
            }
        }

        $res->withHeader('Content-Type', 'text/html')->write($view->render());
    }

    /** The compiled path of WaypointController's route (View::waypointJsTag()), or null if it isn't attached. Memoized. */
    private function resolveWaypointJsPath(): ?string
    {
        if ($this->waypointJsPath === false) {
            $this->waypointJsPath = null;
            foreach ($this->staticRoutes['GET'] ?? [] as $path => $plan) {
                if (($plan['controller'] ?? null) === WaypointController::class) {
                    $this->waypointJsPath = $path;
                    break;
                }
            }
        }
        return $this->waypointJsPath;
    }

    /**
     * Compiled {css, js} filenames for a view or layout name (see ViewAssets::discoverViewNames()), or both null.
     * @return array{css: ?string, js: ?string}
     */
    public function getViewAssets(string $name): array
    {
        return $this->viewAssetsByName[$name] ?? ['css' => null, 'js' => null];
    }

    // -- Listing (OpenAPI) --

    /**
     * A flat summary of every route. 'version'/'unversionedPath' fall back to null/path for a
     * plan predating #[Version] -- what an unversioned route looks like anyway.
     * @return array<int, RouteSummary>
     */
    public function getRoutes(): array
    {
        $routes = [];
        foreach ($this->staticRoutes as $method => $byPath) {
            foreach ($byPath as $path => $plan) {
                $routes[] = $this->toRouteSummary($method, $path, $plan);
            }
        }
        foreach ($this->dynamicRoutes as $method => $plans) {
            foreach ($plans as $plan) {
                $path = $plan['path'] ?? null;
                if (is_string($path)) {
                    $routes[] = $this->toRouteSummary($method, $path, $plan);
                }
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
        $handler = $plan['method'] ?? null;
        $version = $plan['version'] ?? null;
        $unversionedPath = $plan['unversionedPath'] ?? null;
        return (object) [
            'method' => $method,
            'rawPath' => $rawPath,
            'handlerSpec' => [is_string($controller) || is_object($controller) ? $controller : '', is_string($handler) ? $handler : ''],
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

    // -- CLI tasks --

    /**
     * Runs the task named $name: an exact "prefix:name" key, else the unique task whose name or
     * ":name" suffix matches. The manager gets #[Inject] wiring (minus Request/Response) and is
     * called as method($argv, $argc).
     *
     * @param string[] $argv
     */
    public function executeTask(string $name, array $argv = [], int $argc = 0): mixed
    {
        $plan = $this->tasks[$name] ?? $this->findTaskByShortName($name);
        if (!is_array($plan)) {
            throw new RuntimeException("Task '{$name}' not found.");
        }
        $plan = Arr::stringKeyed($plan);

        $managerRef = $plan['manager'] ?? null;
        if (!is_string($managerRef) || !class_exists($managerRef)) {
            // @codeCoverageIgnoreStart
            // only a hand-corrupted cache.
            throw new RuntimeException("Task handler for '{$name}' is invalid.");
            // @codeCoverageIgnoreEnd
        }
        $manager = $this->resolve($managerRef);
        $this->injector->injectPlanned($manager, $plan['propInject'] ?? null);

        $method = $plan['method'] ?? null;
        if (!is_string($method) || !method_exists($manager, $method)) {
            throw new RuntimeException("Task handler for '{$name}' is invalid.");
        }
        return $manager->{$method}($argv, $argc);
    }

    /** @return array<string, mixed>|null */
    private function findTaskByShortName(string $name): ?array
    {
        $matches = [];
        foreach ($this->tasks as $plan) {
            if (!is_array($plan)) {
                continue;
            }
            $plan = Arr::stringKeyed($plan);
            $full = $plan['fullName'] ?? null;
            if (($plan['name'] ?? null) === $name || (is_string($full) && ($full === $name || str_ends_with($full, ":$name")))) {
                $matches[] = $plan;
            }
        }
        if (count($matches) > 1) {
            throw new RuntimeException("Ambiguous task name '{$name}'.");
        }
        return $matches[0] ?? null;
    }
}
