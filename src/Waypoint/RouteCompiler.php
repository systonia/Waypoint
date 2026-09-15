<?php

namespace Waypoint;

use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionProperty;

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
    SimpleXmlFormatter,
    Task
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
     * @param array $classes
     * @return array{staticRoutes: array, dynamicRoutes: array, tasks: array}
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
     * Undocumented function
     *
     * @param array $controllers
     * @return array
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
