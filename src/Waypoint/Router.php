<?php

namespace Waypoint;

use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionProperty;
use RuntimeException;

use Waypoint\Container;
use Waypoint\Enums\RouteType;
use Waypoint\Http\Request;
use Waypoint\Http\Response;
use Waypoint\Http\View;
use Waypoint\Validator;
use Waypoint\Exceptions\ValidationException;
use Waypoint\FileSystem;
use Waypoint\Options\{FileSystemOptions, RendererOptions};
use Waypoint\ViewAssets;
use Waypoint\Attributes\{
    Inject,
    Body,
    Param,
    Query,
    Controller,
    FileFormatter,
    JSONFormatter,
    Manager,
    Middleware,
    SimpleXmlFormatter,
    Task
};

class Router
{
    public array $staticRoutes = [];
    public array $dynamicRoutes = [];
    public array $tasks = [];

    /** {filename => mime} -- GET /assets/{filename} resolves through this, a strict map hit or a 404; the actual bytes are read from FileSystem::getAssetsDirectory() on demand, never held here. */
    private array $viewAssetFiles = [];
    /** {viewName => {css: ?filename, js: ?filename}} -- looked up by name when rendering a View, to emit its asset headers. */
    private array $viewAssetsByName = [];

    private ?Container $container = null;

    private FileSystemOptions $fileSystemOptions;
    private FileSystem $fileSystem;

    // -- Route Management --

    public function addCompiledRoute(array $plan, RouteType $type = RouteType::Unset): void
    {
        // Task plans carry no 'httpMethod' key, so it's only read inside the
        // arms that actually need it instead of unconditionally up front.
        match ($type) {
            RouteType::Dynamic => $this->dynamicRoutes[$plan['httpMethod']][] = $plan,
            RouteType::Static => $this->staticRoutes[$plan['httpMethod']][$plan['path']] = $plan,
            RouteType::Task => $this->tasks[$plan['fullName']] = $plan,
            RouteType::Unset => throw new RuntimeException('addCompiledRoute() requires an explicit RouteType (Static, Dynamic, or Task).')
        };
    }

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

    public function importPlans(array $data): void
    {
        $this->staticRoutes = $data['staticRoutes'] ?? [];
        $this->dynamicRoutes = $data['dynamicRoutes'] ?? [];
        $this->tasks = $data['tasks'] ?? [];
        $this->viewAssetFiles = $data['viewAssetFiles'] ?? [];
        $this->viewAssetsByName = $data['viewAssetsByName'] ?? [];
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
     * @param array $controllers
     * @param array $serviceClasses
     * @param Container|null $container
     * @param array|null $preloadedCache Route data the caller already read
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
            $plans = $this->compileRoutePlans($controllers);
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

        if ($this->tryServeStaticFile($path, $res)) {
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

        $controller = $this->resolveController($route['controller']);
        $this->injectControllerProperties($controller, $route['propInject'], $req, $res);

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
     */
    private function buildRouteHandler(array $route, object $controller, array $params): callable
    {
        $final = function (Request $req, Response $res) use ($route, $controller, $params): void {
            $args = $this->buildMethodArguments($route['argPlan'], $req, $res, $params);
            $result = $controller->{$route['method']}(...$args);
            $this->renderResult($result, $res, $route['formatter'] ?? ['type' => 'json', 'options' => null]);
        };

        return array_reduce(
            array_reverse($route['middlewares'] ?? []),
            function (callable $next, array $mw): callable {
                return function (Request $req, Response $res) use ($mw, $next) {
                    $middleware = $this->resolveController($mw['class']);
                    $this->injectControllerProperties($middleware, $mw['propInject'], $req, $res);
                    $method = $mw['method'];
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

    private function tryServeStaticFile(string $path, Response $res): bool
    {
        if ($this->fileSystemOptions->publicDirectory === null) {
            return false;
        }

        $publicPath = $this->fileSystemOptions->getPublicDirectory();
        $filePath   = realpath($publicPath . $path);

        if (
            $filePath
            && str_starts_with($filePath, realpath($publicPath))
            && is_file($filePath)
        ) {
            $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

            // Feste MIME-Types für kritische Web-Dateien
            $mimeMap = [
                'css' => 'text/css; charset=utf-8',
                'js'  => 'application/javascript; charset=utf-8',
                'mjs' => 'application/javascript; charset=utf-8',
                'json'=> 'application/json; charset=utf-8',
                'svg' => 'image/svg+xml',
                'html'=> 'text/html; charset=utf-8',
            ];

            if (isset($mimeMap[$ext])) {
                $mime = $mimeMap[$ext];
            } else {
                $mime = mime_content_type($filePath) ?: 'application/octet-stream';
            }

            $res->withHeader('Content-Type', $mime)
                ->withHeader('Content-Length', (string) filesize($filePath))
                ->write(file_get_contents($filePath))
                ->send();

            return true;
        }

        return false;
    }

    /**
     * Serves a view's cache-busted CSS/JS through GET /assets/{filename},
     * resolving strictly against the compiled {filename => mime} map --
     * never by constructing a path from the request's own filename, so an
     * unrecognized filename is a 404, not a traversal attempt; $filename is
     * only ever used to read FileSystem::getAssetsDirectory()/{filename}
     * once it's already confirmed to be a known key, never built from
     * unvalidated request input directly. The URL is content-hashed, so a
     * hit can be cached by the browser forever.
     */
    private function tryServeViewAsset(string $path, Response $res): bool
    {
        if (!str_starts_with($path, '/assets/')) {
            return false;
        }

        $filename = substr($path, strlen('/assets/'));
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
            ->write($content)
            ->send();

        return true;
    }

    private function matchRoute(string $httpMethod, string $path): array
    {
        $m = strtoupper($httpMethod);
        $params = [];
        $route = $this->staticRoutes[$m][$path] ?? null;

        if ($route) {
            return [$route, $params];
        }

        foreach ($this->dynamicRoutes[$m] ?? [] as $entry) {
            if (preg_match($entry['regex'], $path, $matches)) {
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
            ->write(json_encode(['error' => 'Not found']))
            ->send();
    }

    private function resolveController(string $class)
    {
        return $this->container ? $this->container->get($class) : new $class();
    }

    private function injectControllerProperties(object $controller, array $propInject, Request $req, Response $res): void
    {
        foreach ($propInject as $p) {
            $propName = $p['name'];
            $type = $p['type'];
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
                    if ($this->container && class_exists($type)) {
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
            $type = $prop->getType()?->getName();
            if (!$type || !class_exists($type) || !$this->container->has($type)) {
                continue;
            }
            $prop->setValue($view, $this->container->get($type));
        }
    }

    private function buildMethodArguments(array $argPlan, Request $req, Response $res, array $params): array
    {
        $args = [];
        foreach ($argPlan as $arg) {
            switch ($arg['inject']) {
                case 'Request':
                    $args[] = $req;
                    break;
                case 'Response':
                    $args[] = $res;
                    break;
                case 'Route':
                    $args[] = $params[$arg['name']] ?? null;
                    break;
                case 'Query':
                    $args[] = $req->query($arg['name']);
                    break;
                case 'Body':
                    $dto = new $arg['class']($req->body());
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
                    // Request::$body is declared `array` (see Request::capture()),
                    // so it can never actually be anything else here -- no
                    // "not an array" branch is reachable to guard against.
                    $items = $req->body();
                    $validator = ($arg['validate'] ?? false) ? new Validator() : null;
                    $collection = [];
                    $errors = [];
                    foreach ($items as $index => $item) {
                        $dto = new $arg['class']($item);
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
                    $val = $params[$arg['name']] ?? $req->query($arg['name']) ?? null;
                    settype($val, $arg['type']);
                    $args[] = $val;
                    break;
                default:
                    $args[] = null;
                    break;
            }
        }
        return $args;
    }

    private function renderResult($result, Response $res, array $formatter): void
    {
        $type = $formatter['type'] ?? 'json';
        $options = $formatter['options'] ?? [];

        // HTML View
        if ($result instanceof View) {
            $this->injectViewProperties($result);

            $assets = $this->getViewAssets($result->getViewName());
            // Always set, even when both are null: a full (non-partial)
            // render's layout can call $result->assetTags()/scopeAttribute()
            // itself, since it has no client-side JS running yet to read
            // the equivalent response headers below the way a partial-swap
            // navigation does.
            $result->setAssets($assets);

            if ($assets['css'] !== null || $assets['js'] !== null) {
                // The view's own name too, not just its asset filenames --
                // a client applying these needs it to set data-view on the
                // swapped container, which is what ViewAssets::scopeCss()'s
                // [data-view="..."] selectors actually match against.
                $res->withHeader('X-Waypoint-View-Name', $result->getViewName());
                if ($assets['css'] !== null) {
                    $res->withHeader('X-Waypoint-View-Css', $assets['css']);
                }
                if ($assets['js'] !== null) {
                    $res->withHeader('X-Waypoint-View-Js', $assets['js']);
                }
            }

            $res->withHeader('Content-Type', 'text/html')
                ->write($result->render())
                ->send();
            return;
        }

        // File Formatter
        if ($type === FileFormatter::class || $type === 'file') {
            $this->renderFileResult($result, $res, $options);
            return;
        }

        // XML Formatter
        if ($type === SimpleXmlFormatter::class || $type === 'xml') {
            $this->renderXmlResult($result, $res);
            return;
        }

        // JSON (default)
        $res->withHeader('Content-Type', 'application/json')
            ->write(json_encode($result))
            ->send();
    }

    private function renderFileResult($result, Response $res, array $options): void
    {
        $mimetype = $options['mimetype'] ?? 'application/octet-stream';
        $res->withHeader('Content-Type', $mimetype);

        if (!empty($options['download'])) {
            $filename = $options['filename'] ?? (is_string($result) ? basename($result) : 'download.bin');
            $res->withHeader('Content-Disposition', "attachment; filename=\"$filename\"");
        }

        if (is_string($result) && is_file($result)) {
            $res->write(file_get_contents($result))->send();
        } else {
            $res->write(is_scalar($result) ? $result : json_encode($result))->send();
        }
    }

    private function renderXmlResult($result, Response $res): void
    {
        $res->withHeader('Content-Type', 'application/xml');
        $xml = simplexml_load_string('<root/>');
        $arrayResult = is_array($result) ? $result : (array) $result;
        array_walk_recursive($arrayResult, function ($v, $k) use ($xml) {
            $xml->addChild($k, $v);
        });
        $res->write($xml->asXML())->send();
    }

    public function compileRoutePlans(array $classes): array
    {
        $staticRoutes = [];
        $dynamicRoutes = [];
        $tasks = [];

        foreach ($classes as $class) {
            if (!class_exists($class)) {
                continue;
            }
            $rc = new ReflectionClass($class);

            // find first class-level attribute that we support
            $classAttrInstance = null;
            foreach ($rc->getAttributes() as $attr) {
                $instance = $attr->newInstance();
                if ($instance instanceof Controller || $instance instanceof Manager) {
                    $classAttrInstance = $instance;
                    break;
                }
            }

            if (!$classAttrInstance) {
                // not a controller or manager; skip
                continue;
            }

            // Derive a normalized prefix from the attribute (supports getPath/getPrefix/getName)
            $prefix = $this->normalizePrefix($classAttrInstance);

            // Dispatch (easy to extend with more kinds later)
            match (true) {
                $classAttrInstance instanceof Controller
                => $this->compileController($rc, $class, $prefix, $staticRoutes, $dynamicRoutes),

                $classAttrInstance instanceof Manager
                => $this->compileManager($rc, $class, $prefix, $tasks),

                // @codeCoverageIgnoreStart
                // Unreachable: the loop above only ever sets
                // $classAttrInstance to a Controller or Manager instance (or
                // leaves it null, which continue's past this match entirely).
                default => null, // future-proof
                // @codeCoverageIgnoreEnd
            };
        }

        return [
            'staticRoutes' => $staticRoutes,
            'dynamicRoutes' => $dynamicRoutes,
            'tasks' => $tasks,
        ];
    }

    /**
     * Normalize prefix from a class attribute instance (Controller|Manager).
     * Accepts getPath(), getPrefix(), or getName() — first one found wins.
     */
    private function normalizePrefix(object $attr): string
    {
        $raw = '';
        foreach (['getPath', 'getPrefix', 'getName'] as $method) {
            if (method_exists($attr, $method)) {
                $raw = (string) $attr->{$method}();
                break;
            }
        }
        // For controllers, treat prefix as a URL path; for managers it’s a plain name.
        // We standardize to "string" here and let the per-kind compilers format as needed.
        return trim((string) $raw);
    }

    /**
     * Compile HTTP routes from a Controller class.
     * Populates $staticRoutes and $dynamicRoutes by reference.
     */
    private function compileController(
        ReflectionClass $rc,
        string|object $controller,
        string $prefix,
        array &$staticRoutes,
        array &$dynamicRoutes
    ): void {
        // Controller path prefix (URL-ish)
        $prefixPath = $prefix !== '' ? '/' . trim($prefix, '/') : '';

        // Pre-compute property injections once per controller
        $propInject = $this->collectPropertyInjections($rc);
        // Pre-compute class-level middleware once per controller; it runs
        // before any method-level middleware on every route below.
        $classMiddlewares = $this->collectMiddlewares($rc->getAttributes(Middleware::class));

        foreach ($rc->getMethods() as $method) {
            [$routeAttr, $formatterAttr] = $this->extractRouteAndFormatter($method);
            if (!$routeAttr) {
                continue;
            }

            $methodPath = $routeAttr->getPath() ?: '';
            $combined = rtrim($prefixPath, '/') . '/' . ltrim($methodPath, '/');
            $path = '/' . trim($combined, '/');

            $dynamic = strpos($path, '{') !== false;
            $regex = $dynamic
                ? '#^' . preg_replace('#\{(\w+)\}#', '(?P<\1>[^/]+)', $path) . '$#'
                : null;

            $argPlan = $this->buildArgPlan($method);
            $formatter = $this->normalizeFormatter($formatterAttr);
            $methodMiddlewares = $this->collectMiddlewares($method->getAttributes(Middleware::class));
            $middlewares = [...$classMiddlewares, ...$methodMiddlewares];

            $plan = [
                'httpMethod' => strtoupper($routeAttr->getHttpMethod()),
                'path' => $path,
                'regex' => $regex,
                'controller' => $controller,
                'method' => $method->getName(),
                'argPlan' => $argPlan,
                'propInject' => $propInject,
                'formatter' => $formatter,
                'middlewares' => $middlewares,
                'throws' => [],
            ];

            if ($dynamic) {
                $dynamicRoutes[$plan['httpMethod']][] = $plan;
            } else {
                $staticRoutes[$plan['httpMethod']][$path] = $plan;
            }
        }
    }

    /**
     * Compile named tasks from a Manager class.
     * Populates $tasks by reference, keyed by full task name (prefix:name).
     *
     * All tasks behave like "static" entries (no HTTP method, direct lookup by name).
     */
    private function compileManager(
        ReflectionClass $rc,
        string|object $manager,
        string $namePrefix,
        array &$tasks
    ): void {
        // normalize manager prefix (no slashes, plain token)
        $normalizedPrefix = trim($namePrefix, " \t\n\r\0\x0B/");

        // Pre-compute property injections once per manager
        $propInject = $this->collectPropertyInjections($rc);

        foreach ($rc->getMethods() as $method) {
            // find a #[Task('name')] on method
            $taskAttr = null;
            foreach ($method->getAttributes() as $attr) {
                $instance = $attr->newInstance();
                if ($instance instanceof Task) {
                    $taskAttr = $instance;
                    break;
                }
            }
            if (!$taskAttr) {
                continue;
            }

            $taskName = $this->extractTaskName($taskAttr);
            if ($taskName === '') {
                // ignore unnamed tasks
                continue;
            }

            $fullName = $normalizedPrefix !== '' ? $normalizedPrefix . ':' . $taskName : $taskName;

            // Tasks share the same arg/prop injection logic
            $argPlan = $this->buildArgPlan($method);

            $plan = [
                'name' => $taskName,
                'fullName' => $fullName,
                'manager' => $manager,
                'method' => $method->getName(),
                'argPlan' => $argPlan,
                'propInject' => $propInject,
                // Optional: allow a formatter attribute to influence output of task runs
                'formatter' => $this->extractFormatterOnly($method),
                'throws' => [],
            ];

            // Tasks are "static-like" — direct lookup by exact name
            $tasks[$fullName] = $plan;
        }
    }

    /** Collect #[Inject] property metadata once per class. */
    private function collectPropertyInjections(ReflectionClass $rc): array
    {
        $propInject = [];
        foreach ($rc->getProperties() as $prop) {
            if ($prop->getAttributes(Inject::class)) {
                $type = $prop->getType()?->getName();
                $propInject[] = [
                    'name' => $prop->getName(),
                    'type' => $type,
                ];
            }
        }
        return $propInject;
    }

    /**
     * Collect #[Middleware(...)] attributes (from a controller class or a route
     * method) in declaration order. A bare class name defaults to calling its
     * 'handle' method. Each Middleware class is container-resolved at dispatch
     * time so it can itself use #[Inject].
     */
    private function collectMiddlewares(array $attributes): array
    {
        $middlewares = [];
        foreach ($attributes as $attr) {
            $instance = $attr->newInstance();
            $callable = $instance->callable;
            if (!isset($callable[0]) || !class_exists($callable[0])) {
                continue;
            }
            $middlewares[] = [
                'class' => $callable[0],
                'method' => $callable[1] ?? 'handle',
                // Computed once at compile time so #[Inject] works on middleware
                // classes too, without reflecting on every request.
                'propInject' => $this->collectPropertyInjections(new ReflectionClass($callable[0])),
            ];
        }
        return $middlewares;
    }

    /**
     * Build argument injection plan for a method.
     * Mirrors the original logic (Request/Response/Body/Query/Route/Scalar/Unknown).
     */
    private function buildArgPlan(ReflectionMethod $method): array
    {
        $argPlan = [];

        foreach ($method->getParameters() as $param) {
            $type = match (true) {
                is_null($param->getType()) => null,
                method_exists($param->getType(), 'getName') => $param->getType()->getName(),
                default => null,
            };

            $bodyAttr = $param->getAttributes(Body::class)[0] ?? null;
            $queryAttr = $param->getAttributes(Query::class)[0] ?? null;
            $routeAttrP = $param->getAttributes(Param::class)[0] ?? null;

            if ($type === Request::class) {
                $argPlan[] = ['inject' => 'Request'];
            } elseif ($type === Response::class) {
                $argPlan[] = ['inject' => 'Response'];
            } elseif ($bodyAttr && $type && class_exists($type)) {
                $argPlan[] = ['inject' => 'Body', 'class' => $type, 'validate' => true];
            } elseif ($bodyAttr && in_array($type, ['array', 'iterable'], true) && ($of = $bodyAttr->newInstance()->of) && class_exists($of)) {
                $argPlan[] = ['inject' => 'BodyCollection', 'class' => $of, 'validate' => true];
            } elseif ($queryAttr) {
                $attrInstance = $queryAttr->newInstance();
                $key = $attrInstance->name ?? $param->getName();
                $argPlan[] = ['inject' => 'Query', 'name' => $key];
            } elseif ($routeAttrP) {
                $attrInstance = $routeAttrP->newInstance();
                $key = $attrInstance->name ?? $param->getName();
                $argPlan[] = ['inject' => 'Route', 'name' => $key];
            } elseif ($type && in_array($type, ['string', 'int', 'float', 'bool'], true)) {
                $key = $param->getName();
                $argPlan[] = ['inject' => 'Scalar', 'name' => $key, 'type' => $type];
            } else {
                $argPlan[] = ['inject' => 'Unknown'];
            }
        }

        return $argPlan;
    }

    /** Extract both route attribute (must have getPath + getHttpMethod) and optional formatter. */
    private function extractRouteAndFormatter(ReflectionMethod $method): array
    {
        $routeAttr = null;
        $formatter = null;

        foreach ($method->getAttributes() as $attr) {
            $instance = $attr->newInstance();
            if (method_exists($instance, 'getPath') && method_exists($instance, 'getHttpMethod')) {
                $routeAttr = $instance;
            }
            if (
                $instance instanceof FileFormatter
                || $instance instanceof SimpleXmlFormatter
                || $instance instanceof JSONFormatter
            ) {
                $formatter = $instance;
            }
        }

        return [$routeAttr, $formatter];
    }

    /** Extract only a formatter (for tasks; optional). */
    private function extractFormatterOnly(ReflectionMethod $method): array
    {
        $formatterAttr = null;
        foreach ($method->getAttributes() as $attr) {
            $instance = $attr->newInstance();
            if (
                $instance instanceof FileFormatter
                || $instance instanceof SimpleXmlFormatter
                || $instance instanceof JSONFormatter
            ) {
                $formatterAttr = $instance;
            }
        }
        return self::normalizeFormatter($formatterAttr);
    }

    /** Normalize formatter into a plan-friendly array. */
    private function normalizeFormatter(?object $formatterAttr): array
    {
        return $formatterAttr
            ? ['type' => get_class($formatterAttr), 'options' => get_object_vars($formatterAttr)]
            : ['type' => 'json', 'options' => null];
    }

    /** Extract task name from a #[Task(...)] attribute instance defensively. */
    private function extractTaskName(object $taskAttr): string
    {
        // Support common shapes: ->getName(), public $name, or ->name()
        foreach (['getName', 'name', '__toString'] as $method) {
            if (method_exists($taskAttr, $method)) {
                $val = (string) $taskAttr->{$method}();
                if ($val !== '') {
                    return $val;
                }
            }
        }
        // Fallback for a *public* property named "name" -- checked via
        // Reflection rather than `$taskAttr->name` directly, since that
        // would fatal with "Cannot access private property" for an
        // attribute (like Task itself) that declares $name as private.
        if (property_exists($taskAttr, 'name')) {
            $prop = new ReflectionProperty($taskAttr, 'name');
            if ($prop->isPublic()) {
                $value = $prop->getValue($taskAttr);
                if (is_string($value)) {
                    return $value;
                }
            }
        }
        return '';
    }


    /**
     * The compiled {css, js} cache-busted filenames for the given
     * view/layout template basename (a '*.php' file's name without the
     * extension, in the configured RendererOptions directory), or
     * {css: null, js: null} if it has none. Public so View can look up its
     * *layout's* own assets (View::$layoutAssets) the same way
     * renderResult() already looks up the view's own -- ViewAssets::compile()
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

    public function getRoutes(): array
    {
        $routes = [];
        foreach ($this->staticRoutes as $method => $byPath) {
            foreach ($byPath as $path => $plan) {
                $routes[] = (object) [
                    'method' => $method,
                    'rawPath' => $path,
                    'handlerSpec' => [$plan['controller'], $plan['method']],
                ];
            }
        }
        foreach ($this->dynamicRoutes as $method => $plans) {
            foreach ($plans as $plan) {
                $routes[] = (object) [
                    'method' => $method,
                    'rawPath' => $plan['path'],
                    'handlerSpec' => [$plan['controller'], $plan['method']],
                ];
            }
        }
        return $routes;
    }

    public static function exportAllAttributes(array $controllers): array
    {
        $result = [];
        foreach ($controllers as $className) {
            if (!class_exists($className))
                continue;
            $rc = new ReflectionClass($className);

            // Class-level
            $result[$className]['__class'] = array_map(
                fn($attr) => [
                    'name' => $attr->getName(),
                    'args' => $attr->getArguments(),
                ],
                $rc->getAttributes()
            );

            // Property-level
            foreach ($rc->getProperties() as $prop) {
                $result[$className]['properties'][$prop->getName()] = array_map(
                    fn($attr) => [
                        'name' => $attr->getName(),
                        'args' => $attr->getArguments(),
                    ],
                    $prop->getAttributes()
                );
            }

            // Method/Param-level
            foreach ($rc->getMethods() as $method) {
                $result[$className]['methods'][$method->getName()]['__method'] = array_map(
                    fn($attr) => [
                        'name' => $attr->getName(),
                        'args' => $attr->getArguments(),
                    ],
                    $method->getAttributes()
                );
                foreach ($method->getParameters() as $param) {
                    $type = $param->getType();
                    $result[$className]['methods'][$method->getName()]['parameters'][$param->getName()] = [
                        'attributes' => array_map(
                            fn($attr) => [
                                'name' => $attr->getName(),
                                'args' => $attr->getArguments(),
                            ],
                            $param->getAttributes()
                        ),
                        // Only a plain named type (string, int, Some\Class, ...) is
                        // reported; union/intersection types come through as null
                        // since OpenAPI has no single-type slot to put them in.
                        'type' => $type instanceof ReflectionNamedType ? $type->getName() : null,
                        'nullable' => $type ? $type->allowsNull() : true,
                        'hasDefault' => $param->isDefaultValueAvailable(),
                    ];
                }
            }
        }
        return $result;
    }

    public function findRoute(array $routes, string $method, string $path): ?object
    {
        foreach ($routes as $route) {
            if ($route->rawPath === $path && $route->method === $method) {
                return $route;
            }
        }
        return null;
    }

    public function executeTask(string $name, array $argv = [], int $argc = 0): mixed
    {
        // 1) Try exact key first (full name, e.g. "prefix:task" or plain "task")
        $plan = $this->tasks[$name] ?? null;

        // 2) Fallback: scan for a unique match by fullName/name or suffix ":name"
        if (!$plan) {
            $matches = [];
            foreach ($this->tasks as $fullName => $p) {
                if (!is_array($p))
                    continue;
                $short = $p['name'] ?? null;
                $full = $p['fullName'] ?? null;

                if ($full === $name || $short === $name || ($full && str_ends_with($full, ':' . $name))) {
                    $matches[] = $p;
                }
            }
            if (count($matches) === 1) {
                $plan = $matches[0];
            } elseif (count($matches) > 1) {
                throw new RuntimeException("Ambiguous task name '{$name}'.");
            }
        }

        if (!$plan) {
            throw new RuntimeException("Task '{$name}' not found.");
        }

        // Resolve manager (container-aware)
        $manager = $this->resolveController($plan['manager'] ?? '');

        // Inject properties (DI) — skip Request/Response for tasks
        $this->injectTaskProperties($manager, $plan['propInject'] ?? []);

        // Call EXACTLY with ($argv, $argc)
        $method = $plan['method'] ?? null;
        if (!$method || !method_exists($manager, $method)) {
            throw new RuntimeException("Task handler for '{$name}' is invalid.");
        }

        return $manager->{$method}($argv, $argc);
    }

    /** Inject DI for tasks, but never Request/Response (no HTTP in tasks). */
    private function injectTaskProperties(object $target, array $propInject): void
    {
        foreach ($propInject as $p) {
            $propName = $p['name'] ?? null;
            $type = $p['type'] ?? null;
            if (!$propName)
                continue;

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
                    if ($this->container && $type && class_exists($type)) {
                        $refProp->setValue($target, $this->container->get($type));
                    }
            }
        }
    }
}
