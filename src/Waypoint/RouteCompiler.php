<?php

namespace Waypoint;

use DateTimeImmutable;
use DateTimeZone;
use Deprecated;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;
use Waypoint\Enums\Message;
use Waypoint\Http\{MiddlewareBase, Request, Response};
use Waypoint\Routing\PropertyInjector;
use Waypoint\Attributes\{
    Authenticated, Body, Controller, FileFormatter, Inject, JSONFormatter, Manager, Middleware, NoGzip,
    Param, Permissions, Query, Role, RouteAttribute, SimpleXmlFormatter, SkipCsrf, Sunset, Task, Version
};

/**
 * Reflects #[Controller]/#[Manager] classes once into the plain-array route/
 * task plans Router dispatches against (and FileSystem caches). Everything a
 * request needs -- argument binding, injections, middleware, auth/CSRF flags,
 * headers -- is resolved here so dispatch never reflects.
 */
final class RouteCompiler
{
    private const FORMATTERS = [FileFormatter::class, SimpleXmlFormatter::class, JSONFormatter::class];

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
            $controller = self::attribute($rc, Controller::class);
            if ($controller !== null) {
                $this->compileController($rc, $class, $controller->getPath(), $staticRoutes, $dynamicRoutes);
                continue;
            }
            $manager = self::attribute($rc, Manager::class);
            if ($manager !== null) {
                $this->compileManager($rc, $class, $manager->getName(), $tasks);
            }
        }

        return ['staticRoutes' => $staticRoutes, 'dynamicRoutes' => $dynamicRoutes, 'tasks' => $tasks];
    }

    /**
     * @param ReflectionClass<object> $rc
     * @param array<string, array<string, RoutePlan>> $staticRoutes
     * @param array<string, array<int, RoutePlan>> $dynamicRoutes
     */
    private function compileController(ReflectionClass $rc, string $controller, string $prefixPath, array &$staticRoutes, array &$dynamicRoutes): void
    {
        $propInject = $this->collectPropertyInjections($rc);
        $classMiddlewares = $this->collectMiddlewares($rc->getAttributes(Middleware::class));

        foreach ($rc->getMethods() as $method) {
            $routeAttr = self::lastAttribute($method, RouteAttribute::class, ReflectionAttribute::IS_INSTANCEOF);
            if ($routeAttr === null) {
                continue;
            }

            $httpMethod = strtoupper($routeAttr->getHttpMethod());
            $unversionedPath = '/' . trim(rtrim($prefixPath, '/') . '/' . ltrim($routeAttr->getPath(), '/'), '/');
            $version = self::inherited($rc, $method, Version::class)?->value;
            $path = $version !== null ? "/$version$unversionedPath" : $unversionedPath;
            if ($version === null) {
                $this->warn(Message::RouteUnversioned, method: $httpMethod, path: $path);
            }

            $dynamic = str_contains($path, '{');
            $role = self::inherited($rc, $method, Role::class)?->role;
            $permissions = self::inherited($rc, $method, Permissions::class)?->permissions;
            $permissions = $permissions !== null ? array_values(array_filter($permissions, 'is_string')) : null;
            $sunsetDate = self::inherited($rc, $method, Sunset::class)?->date;

            $plan = [
                'httpMethod' => $httpMethod,
                'path' => $path,
                'regex' => $dynamic ? '#^' . preg_replace('#\{(\w+)\}#', '(?P<\1>[^/]+)', $path) . '$#' : null,
                'controller' => $controller,
                'method' => $method->getName(),
                'argPlan' => $this->buildArgPlan($method),
                'propInject' => $propInject,
                'formatter' => self::normalizeFormatter(self::findFormatter($method)),
                'middlewares' => [...$classMiddlewares, ...$this->collectMiddlewares($method->getAttributes(Middleware::class))],
                'gzip' => !self::hasEither($rc, $method, NoGzip::class),
                'skipCsrf' => self::hasEither($rc, $method, SkipCsrf::class),
                'version' => $version,
                'unversionedPath' => $unversionedPath,
                // PHP's native #[\Deprecated]; presence at either level is the whole signal.
                'deprecated' => self::hasEither($rc, $method, Deprecated::class),
                'sunsetHeader' => $sunsetDate !== null ? $this->formatSunsetHeader($sunsetDate, $httpMethod, $path) : null,
                // #[Role]/#[Permissions] imply #[Authenticated].
                'authenticated' => self::hasEither($rc, $method, Authenticated::class) || $role !== null || $permissions !== null,
                'role' => $role,
                'permissions' => $permissions,
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
     * @param ReflectionClass<object> $rc
     * @param array<string, TaskPlan> $tasks Keyed by "prefix:name" (or just "name").
     */
    private function compileManager(ReflectionClass $rc, string $manager, string $namePrefix, array &$tasks): void
    {
        $propInject = $this->collectPropertyInjections($rc);

        foreach ($rc->getMethods() as $method) {
            $name = self::attribute($method, Task::class)?->getName();
            if ($name === null || $name === '') {
                continue;
            }
            $fullName = $namePrefix !== '' ? "$namePrefix:$name" : $name;
            $tasks[$fullName] = [
                'name' => $name,
                'fullName' => $fullName,
                'manager' => $manager,
                'method' => $method->getName(),
                'argPlan' => $this->buildArgPlan($method),
                'propInject' => $propInject,
                'formatter' => self::normalizeFormatter(self::findFormatter($method)),
                'throws' => [],
            ];
        }
    }

    /**
     * @template T of object
     * @param ReflectionClass<object>|ReflectionMethod|ReflectionParameter $target
     * @param class-string<T> $attribute
     * @return T|null
     */
    private static function attribute(ReflectionClass|ReflectionMethod|ReflectionParameter $target, string $attribute, int $flags = 0): ?object
    {
        $attrs = $target->getAttributes($attribute, $flags);
        return $attrs === [] ? null : $attrs[0]->newInstance();
    }

    /**
     * Like attribute(), but the last declared one wins.
     * @template T of object
     * @param class-string<T> $attribute
     * @return T|null
     */
    private static function lastAttribute(ReflectionMethod $target, string $attribute, int $flags = 0): ?object
    {
        $attrs = $target->getAttributes($attribute, $flags);
        return $attrs === [] ? null : $attrs[array_key_last($attrs)]->newInstance();
    }

    /**
     * Method-level wins over class-level, else null -- the shared precedence of #[Version]/#[Sunset]/#[Role]/#[Permissions].
     * @template T of object
     * @param ReflectionClass<object> $rc
     * @param class-string<T> $attribute
     * @return T|null
     */
    private static function inherited(ReflectionClass $rc, ReflectionMethod $method, string $attribute): ?object
    {
        return self::attribute($method, $attribute) ?? self::attribute($rc, $attribute);
    }

    /**
     * @param ReflectionClass<object> $rc
     * @param class-string $attribute
     */
    private static function hasEither(ReflectionClass $rc, ReflectionMethod $method, string $attribute): bool
    {
        return $method->getAttributes($attribute) !== [] || $rc->getAttributes($attribute) !== [];
    }

    /** The last formatter attribute on $method, if any. */
    private static function findFormatter(ReflectionMethod $method): ?object
    {
        $found = null;
        foreach ($method->getAttributes() as $attr) {
            if (in_array($attr->getName(), self::FORMATTERS, true)) {
                $found = $attr->newInstance();
            }
        }
        return $found;
    }

    /** @return FormatterSpec */
    private static function normalizeFormatter(?object $formatter): array
    {
        if ($formatter === null) {
            return ['type' => 'json', 'options' => null];
        }
        $options = [];
        foreach (get_object_vars($formatter) as $key => $value) {
            if (is_string($key)) {
                $options[$key] = $value;
            }
        }
        return ['type' => get_class($formatter), 'options' => $options];
    }

    /** RFC 8594 Sunset is an HTTP-date; a malformed 'YYYY-MM-DD' is logged and skipped rather than failing the route. */
    private function formatSunsetHeader(string $date, string $httpMethod, string $path): ?string
    {
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('UTC'));
        if ($dt === false) {
            $this->warn(Message::RouteInvalidSunsetDate, method: $httpMethod, path: $path, date: $date);
            return null;
        }
        return $dt->format('D, d M Y H:i:s \G\M\T');
    }

    /** Compile-time warnings need a live App to resolve LoggerOptions; a Router built without one (tests) just stays silent. */
    private function warn(Message $message, string ...$vars): void
    {
        if (Waypoint::getInstance() !== null) {
            (new Logger())->warning($message->format($vars));
        }
    }

    /**
     * @param ReflectionClass<object> $rc
     * @return array<int, PropInjectEntry>
     */
    private function collectPropertyInjections(ReflectionClass $rc): array
    {
        $propInject = [];
        foreach ($rc->getProperties() as $prop) {
            if ($prop->getAttributes(Inject::class) !== []) {
                $propInject[] = ['name' => $prop->getName(), 'type' => PropertyInjector::namedType($prop)];
            }
        }
        return $propInject;
    }

    /**
     * #[Middleware(...)] entries in declaration order. Only a MiddlewareBase subclass via its
     * final handle() is accepted; anything else is skipped at compile time rather than crashing boot.
     *
     * @param ReflectionAttribute<Middleware>[] $attributes
     * @return array<int, MiddlewareEntry>
     */
    private function collectMiddlewares(array $attributes): array
    {
        $middlewares = [];
        foreach ($attributes as $attr) {
            [$class, $method] = $attr->newInstance()->callable + [1 => 'handle'];
            if ($method !== 'handle' || !class_exists($class) || !self::extendsMiddlewareBase($class)) {
                continue;
            }
            $middlewares[] = [
                'class' => $class,
                'method' => $method,
                'propInject' => $this->collectPropertyInjections(new ReflectionClass($class)),
            ];
        }
        return $middlewares;
    }

    /**

     * Wrapped so PHPStan doesn't narrow the caller's $class to class-string<MiddlewareBase>, which ReflectionClass<object> would then refuse.

     * @param class-string $class

     */
    private static function extendsMiddlewareBase(string $class): bool
    {
        return is_subclass_of($class, MiddlewareBase::class);
    }

    /**
     * How each method parameter is bound at dispatch (see Routing\ArgumentResolver).
     * @return array<int, array<string, mixed>>
     */
    private function buildArgPlan(ReflectionMethod $method): array
    {
        $argPlan = [];
        foreach ($method->getParameters() as $param) {
            $type = PropertyInjector::namedType($param);
            $body = self::attribute($param, Body::class);
            $of = $body?->of;

            $argPlan[] = match (true) {
                $type === Request::class => ['inject' => 'Request'],
                $type === Response::class => ['inject' => 'Response'],
                $body !== null && $type !== null && class_exists($type) => ['inject' => 'Body', 'class' => $type, 'validate' => true],
                $body !== null && in_array($type, ['array', 'iterable'], true) && $of !== null && class_exists($of) => ['inject' => 'BodyCollection', 'class' => $of, 'validate' => true],
                ($query = self::attribute($param, Query::class)) !== null => ['inject' => 'Query', 'name' => $query->name ?? $param->getName()],
                ($route = self::attribute($param, Param::class)) !== null => ['inject' => 'Route', 'name' => $route->name ?? $param->getName()],
                in_array($type, ['string', 'int', 'float', 'bool'], true) => ['inject' => 'Scalar', 'name' => $param->getName(), 'type' => $type],
                default => ['inject' => 'Unknown'],
            };
        }
        return $argPlan;
    }

    /**
     * Every attribute on every controller, property, method and parameter, as
     * plain {name, args} entries plus each parameter's type -- what
     * OpenAPIGenerator reads instead of reflecting at request time.
     *
     * @param class-string[] $controllers
     * @return array<class-string, mixed>
     */
    public static function exportAllAttributes(array $controllers): array
    {
        $result = [];
        foreach ($controllers as $className) {
            if (!class_exists($className)) {
                continue;
            }
            $rc = new ReflectionClass($className);
            $result[$className]['__class'] = self::attributeEntries($rc->getAttributes());

            foreach ($rc->getProperties() as $prop) {
                $result[$className]['properties'][$prop->getName()] = self::attributeEntries($prop->getAttributes());
            }

            foreach ($rc->getMethods() as $method) {
                $result[$className]['methods'][$method->getName()]['__method'] = self::attributeEntries($method->getAttributes());
                foreach ($method->getParameters() as $param) {
                    $type = $param->getType();
                    $result[$className]['methods'][$method->getName()]['parameters'][$param->getName()] = [
                        'attributes' => self::attributeEntries($param->getAttributes()),
                        'type' => PropertyInjector::namedType($param),
                        'nullable' => $type === null || $type->allowsNull(),
                        'hasDefault' => $param->isDefaultValueAvailable(),
                    ];
                }
            }
        }
        return $result;
    }

    /**
     * @param ReflectionAttribute<object>[] $attributes
     * @return list<AttributeEntry>
     */
    private static function attributeEntries(array $attributes): array
    {
        $entries = [];
        foreach ($attributes as $attr) {
            $entries[] = ['name' => $attr->getName(), 'args' => $attr->getArguments()];
        }
        return $entries;
    }
}
