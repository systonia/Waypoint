<?php

namespace Waypoint;

use DateTimeImmutable;
use DateTimeZone;
use Deprecated;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionProperty;

use Waypoint\Enums\Message;
use Waypoint\Http\Request;
use Waypoint\Http\Response;
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
    NoGzip,
    RouteAttribute,
    SimpleXmlFormatter,
    Sunset,
    Task,
    Version
};

/**
 * Reflects over a set of controller/manager classes and compiles them into
 * the plain-array route/task plans Router actually dispatches against.
 * Kept separate from Router itself: "how a #[Controller]/#[Manager]
 * class's attributes turn into a route plan" is a compile-time concern with
 * no knowledge of a live request, distinct from Router's own job of
 * matching and dispatching an already-compiled plan. Router::exportPlans()/
 * importPlans() are what persist this compiler's output to/from the
 * FileSystem cache; this class has no idea that cache exists.
 */
final class RouteCompiler
{
    /**
     * @param class-string[] $classes
     * @return array{staticRoutes: array<string, array<string, RoutePlan>>, dynamicRoutes: array<string, array<int, RoutePlan>>, tasks: array<string, TaskPlan>}
     */
    public function compile(array $classes): array
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

                // Always Manager, not just a generic fallback: the loop
                // above only ever sets $classAttrInstance to a Controller
                // or a Manager instance (or leaves it null, which continue's
                // past this match entirely) -- Controller was just ruled
                // out above, so nothing else reaches this arm. Written as
                // `default` rather than `instanceof Manager` because that
                // instanceof would be statically always-true and unusable
                // (PHPStan rejects a condition it can prove is redundant).
                default => $this->compileManager($rc, $class, $prefix, $tasks),
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
                $value = $attr->{$method}();
                // A dynamic method-name call can't be statically resolved to
                // a return type; every real caller (Controller::getPath(),
                // Manager::getName()) returns string, but this narrows
                // explicitly rather than assuming it.
                $raw = is_string($value) ? $value : '';
                break;
            }
        }
        // For controllers, treat prefix as a URL path; for managers it’s a plain name.
        // We standardize to "string" here and let the per-kind compilers format as needed.
        return trim($raw);
    }

    /**
     * Compile HTTP routes from a Controller class.
     * Populates $staticRoutes and $dynamicRoutes by reference.
     *
     * @param ReflectionClass<object> $rc
     * @param array<string, array<string, RoutePlan>> $staticRoutes
     * @param array<string, array<int, RoutePlan>> $dynamicRoutes
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
        // #[NoGzip] on the class disables compression for every route
        // below regardless of the method's own attributes -- combined
        // with each method's own below into a single 'gzip' bool per
        // route, so Response::maybeCompress() never has to reflect.
        $classHasNoGzip = $rc->getAttributes(NoGzip::class) !== [];

        foreach ($rc->getMethods() as $method) {
            [$routeAttr, $formatterAttr] = $this->extractRouteAndFormatter($method);
            if (!$routeAttr) {
                continue;
            }

            $httpMethod = strtoupper($routeAttr->getHttpMethod());

            $methodPath = $routeAttr->getPath() ?: '';
            $combined = rtrim($prefixPath, '/') . '/' . ltrim($methodPath, '/');
            $unversionedPath = '/' . trim($combined, '/');

            // #[Version] on the method overrides the class's, exactly like
            // #[Sunset] below -- see resolveOverridable(). No attribute at
            // either level (the common case today) means no prefix at all,
            // and $unversionedPath IS the final path.
            $effectiveVersion = $this->resolveOverridable($rc, $method, Version::class, 'value');
            $path = $effectiveVersion !== null ? '/' . $effectiveVersion . $unversionedPath : $unversionedPath;

            if ($effectiveVersion === null) {
                $this->logUnversionedRoute($httpMethod, $path);
            }

            $dynamic = strpos($path, '{') !== false;
            $regex = $dynamic
                ? '#^' . preg_replace('#\{(\w+)\}#', '(?P<\1>[^/]+)', $path) . '$#'
                : null;

            $argPlan = $this->buildArgPlan($method);
            $formatter = $this->normalizeFormatter($formatterAttr);
            $methodMiddlewares = $this->collectMiddlewares($method->getAttributes(Middleware::class));
            $middlewares = [...$classMiddlewares, ...$methodMiddlewares];
            $methodHasNoGzip = $method->getAttributes(NoGzip::class) !== [];

            // PHP's own native #[\Deprecated] (8.4+), not a Waypoint
            // attribute -- there's nothing to "override" the way
            // #[Version]/#[Sunset] have a class-vs-method precedence,
            // since presence alone is the whole signal. The $rc check is
            // effectively always false in practice: PHP itself refuses to
            // let #[\Deprecated] target a class at all (a fatal compile
            // error, not just unenforced) -- kept here anyway in case a
            // future PHP version lifts that restriction, since checking
            // costs nothing and getAttributes() never throws for an
            // attribute that simply isn't present.
            $isDeprecated = $method->getAttributes(Deprecated::class) !== [] || $rc->getAttributes(Deprecated::class) !== [];

            $sunsetDate = $this->resolveOverridable($rc, $method, Sunset::class, 'date');
            $sunsetHeader = $sunsetDate !== null
                ? $this->formatSunsetHeader($sunsetDate, $httpMethod, $path)
                : null;

            $plan = [
                'httpMethod' => $httpMethod,
                'path' => $path,
                'regex' => $regex,
                'controller' => $controller,
                'method' => $method->getName(),
                'argPlan' => $argPlan,
                'propInject' => $propInject,
                'formatter' => $formatter,
                'middlewares' => $middlewares,
                'gzip' => !($classHasNoGzip || $methodHasNoGzip),
                'version' => $effectiveVersion,
                'unversionedPath' => $unversionedPath,
                'deprecated' => $isDeprecated,
                'sunsetHeader' => $sunsetHeader,
                'throws' => [],
            ];

            if ($dynamic) {
                $dynamicRoutes[$httpMethod][] = $plan;
            } else {
                $staticRoutes[$httpMethod][$path] = $plan;
            }
        }
    }

    /**
     * "Method wins over class, else null" resolution shared by #[Version]
     * (property 'value') and #[Sunset] (property 'date') -- both are
     * single-string-payload attributes usable at either level with the
     * exact same override rule, just under a different attribute/property
     * name.
     *
     * @param ReflectionClass<object> $rc
     */
    private function resolveOverridable(ReflectionClass $rc, ReflectionMethod $method, string $attributeClass, string $property): ?string
    {
        $methodAttr = $method->getAttributes($attributeClass)[0] ?? null;
        if ($methodAttr) {
            $value = $methodAttr->newInstance()->{$property};
            return is_string($value) ? $value : null;
        }
        $classAttr = $rc->getAttributes($attributeClass)[0] ?? null;
        if (!$classAttr) {
            return null;
        }
        $value = $classAttr->newInstance()->{$property};
        return is_string($value) ? $value : null;
    }

    /**
     * RFC 8594's Sunset header is an HTTP-date (e.g. "Sat, 31 Dec 2022
     * 00:00:00 GMT"), not a bare 'YYYY-MM-DD' -- computed once here, at
     * compile time, since it never depends on anything about a live
     * request. Returns null (skipping the header entirely, after logging a
     * warning) for a malformed date -- a typo in #[Sunset] shouldn't take
     * the whole route down.
     */
    private function formatSunsetHeader(string $date, string $httpMethod, string $path): ?string
    {
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('UTC'));
        if ($dt === false) {
            $this->logInvalidSunsetDate($httpMethod, $path, $date);
            return null;
        }
        return $dt->format('D, d M Y H:i:s \G\M\T');
    }

    /**
     * Logged once per unversioned HTTP route, only during a real compile --
     * never when a cached routes.php is loaded instead (Router's
     * constructor only ever calls RouteCompiler::compile() on a cache
     * miss), so this never fires per-request. Silently skipped when
     * there's no live App to resolve LoggerOptions through
     * (Waypoint::getInstance() === null, e.g. a Router built directly in a
     * test) -- "usable without an App" is a design goal Router itself
     * already guarantees elsewhere, and Logger has no way to work without
     * one.
     */
    private function logUnversionedRoute(string $httpMethod, string $path): void
    {
        if (Waypoint::getInstance() === null) {
            return;
        }
        (new Logger())->warning(Message::RouteUnversioned->interpolate(method: $httpMethod, path: $path));
    }

    /** Same "no App, no logging" guard as logUnversionedRoute() -- see there. */
    private function logInvalidSunsetDate(string $httpMethod, string $path, string $date): void
    {
        if (Waypoint::getInstance() === null) {
            return;
        }
        (new Logger())->warning(Message::RouteInvalidSunsetDate->interpolate(method: $httpMethod, path: $path, date: $date));
    }

    /**
     * Compile named tasks from a Manager class.
     * Populates $tasks by reference, keyed by full task name (prefix:name).
     *
     * All tasks behave like "static" entries (no HTTP method, direct lookup by name).
     *
     * @param ReflectionClass<object> $rc
     * @param array<string, TaskPlan> $tasks
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

    /**
     * Collect #[Inject] property metadata once per class.
     * @param ReflectionClass<object> $rc
     * @return array<int, PropInjectEntry>
     */
    private function collectPropertyInjections(ReflectionClass $rc): array
    {
        $propInject = [];
        foreach ($rc->getProperties() as $prop) {
            if ($prop->getAttributes(Inject::class)) {
                $propType = $prop->getType();
                $type = $propType instanceof ReflectionNamedType ? $propType->getName() : null;
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
     *
     * @param \ReflectionAttribute<Middleware>[] $attributes
     * @return array<int, MiddlewareEntry>
     */
    private function collectMiddlewares(array $attributes): array
    {
        $middlewares = [];
        foreach ($attributes as $attr) {
            $instance = $attr->newInstance();
            $callable = $instance->callable;
            if (!class_exists($callable[0])) {
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
     * @return array<int, array<string, mixed>>
     */
    private function buildArgPlan(ReflectionMethod $method): array
    {
        $argPlan = [];

        foreach ($method->getParameters() as $param) {
            $paramType = $param->getType();
            // Only a plain named type (string, int, Some\Class, ...) is
            // usable below; union/intersection types (the only other
            // ReflectionType subtypes) have no single name to extract.
            $type = $paramType instanceof ReflectionNamedType ? $paramType->getName() : null;

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

    /**
     * Extract both route attribute (must implement RouteAttribute) and optional formatter.
     * @return array{0: RouteAttribute|null, 1: object|null}
     */
    private function extractRouteAndFormatter(ReflectionMethod $method): array
    {
        $routeAttr = null;
        $formatter = null;

        foreach ($method->getAttributes() as $attr) {
            $instance = $attr->newInstance();
            if ($instance instanceof RouteAttribute) {
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

    /**
     * Extract only a formatter (for tasks; optional).
     * @return FormatterSpec
     */
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

    /**
     * Normalize formatter into a plan-friendly array.
     * @return FormatterSpec
     */
    private function normalizeFormatter(?object $formatterAttr): array
    {
        if ($formatterAttr === null) {
            return ['type' => 'json', 'options' => null];
        }

        // get_object_vars() is typed array<mixed> by PHPStan (it can't
        // guarantee string keys for an arbitrary object) even though a real
        // object's property names are always strings -- narrow explicitly.
        $options = [];
        foreach (get_object_vars($formatterAttr) as $key => $value) {
            if (is_string($key)) {
                $options[$key] = $value;
            }
        }

        return ['type' => get_class($formatterAttr), 'options' => $options];
    }

    /** Extract task name from a #[Task(...)] attribute instance defensively. */
    private function extractTaskName(object $taskAttr): string
    {
        // Support common shapes: ->getName(), public $name, or ->name()
        foreach (['getName', 'name', '__toString'] as $method) {
            if (method_exists($taskAttr, $method)) {
                $val = $taskAttr->{$method}();
                if (is_string($val) && $val !== '') {
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
     * @param class-string[] $controllers
     * @return array<class-string, mixed>
     */
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
}
