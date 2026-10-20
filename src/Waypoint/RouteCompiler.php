<?php

namespace Waypoint;

use DateTimeImmutable;
use DateTimeZone;
use Deprecated;
use InvalidArgumentException;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use Waypoint\Enums\Message;
use Waypoint\Http\{MiddlewareBase, Redirect, Request, Response, View};
use Waypoint\Plugin\{ArgumentBinder, RouteAttributeCompiler};
use Waypoint\Routing\PropertyInjector;
use Waypoint\Attributes\{
    Authenticated, Body, Claims, Controller, FileFormatter, Inject, JSONFormatter, Layout, Manager, Middleware, NoGzip,
    Param, Permissions, Query, Role, RouteAttribute, SimpleXmlFormatter, SkipCsrf, Sunset, Task, Version
};

/**
 * Reflects #[Controller]/#[Manager] classes once into the plain-array route/
 * task plans Router dispatches against (and FileSystem caches). Everything a
 * request needs (argument binding, injections, middleware, auth/CSRF flags,
 * headers) is resolved here so dispatch never reflects.
 */
final class RouteCompiler
{
    private const FORMATTERS = [FileFormatter::class, SimpleXmlFormatter::class, JSONFormatter::class];

    /**
     * @param array<string, RouteAttributeCompiler> $attributeCompilers plugin name => compiler; each result lands in plan['plugins'][name]
     * @param array<string, ArgumentBinder> $binders plugin name => binder, asked after the core attributes and before the implicit scalar binding
     */
    public function __construct(private array $attributeCompilers = [], private array $binders = [])
    {
    }

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
        // A base controller's middlewares wrap its subclasses' routes too, the base's outermost
        $classMiddlewares = [];
        foreach (array_reverse(self::lineage($rc)) as $ancestor) {
            $classMiddlewares = [...$classMiddlewares, ...$this->collectMiddlewares($ancestor->getAttributes(Middleware::class), $ancestor->getName())];
        }

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
            $layout = self::inherited($rc, $method, Layout::class);

            $plan = [
                'httpMethod' => $httpMethod,
                'path' => $path,
                'regex' => $dynamic ? self::pathRegex($path) : null,
                'controller' => $controller,
                'method' => $method->getName(),
                'argPlan' => self::checkedArgPlan($method, $this->buildArgPlan($method)),
                'propInject' => $propInject,
                'formatter' => self::normalizeFormatter(self::findFormatter($method)),
                // A void action builds the response itself: its null is no JSON body
                'void' => in_array((string) $method->getReturnType(), ['void', 'never'], true),
                'middlewares' => [...$classMiddlewares, ...$this->collectMiddlewares($method->getAttributes(Middleware::class), "{$controller}::{$method->getName()}()")],
                'gzip' => !self::hasEither($rc, $method, NoGzip::class),
                'skipCsrf' => self::hasEither($rc, $method, SkipCsrf::class),
                'version' => $version,
                'unversionedPath' => $unversionedPath,
                // PHP's native #[\Deprecated]; presence at either level is the whole signal.
                'deprecated' => self::hasEither($rc, $method, Deprecated::class),
                'sunsetHeader' => $sunsetDate !== null ? $this->formatSunsetHeader($sunsetDate, $httpMethod, $path) : null,
                // #[Layout]: false when none is set, null for #[Layout(null)], else the file
                'layout' => $layout === null ? false : $layout->file,
                // #[Role]/#[Permissions] imply #[Authenticated].
                'authenticated' => self::hasEither($rc, $method, Authenticated::class) || $role !== null || $permissions !== null || self::requiresClaims($method),
                'role' => $role,
                'permissions' => $permissions,
                'throws' => [],
                'plugins' => $this->compilePluginData($rc, $method),
            ];

            // Same method and path (or the same pattern): one of the two would never be reached
            $existing = $dynamic
                ? array_find($dynamicRoutes[$httpMethod] ?? [], fn (array $other): bool => $other['regex'] === $plan['regex'])
                : $staticRoutes[$httpMethod][$path] ?? null;
            if ($existing !== null) {
                $other = is_string($existing['controller']) ? $existing['controller'] : $existing['controller']::class;
                throw new InvalidArgumentException("Duplicate route {$httpMethod} {$path}: {$other}::{$existing['method']}() and {$controller}::{$method->getName()}().");
            }
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
     * Each plugin's RouteAttributeCompiler output under its own name; plugins that stored nothing are omitted.
     * @param ReflectionClass<object> $rc
     * @return array<string, array<string, mixed>>
     */
    private function compilePluginData(ReflectionClass $rc, ReflectionMethod $method): array
    {
        $data = [];
        foreach ($this->attributeCompilers as $name => $compiler) {
            $compiled = $compiler->compile($rc, $method);
            if ($compiled !== []) {
                $data[$name] = $compiled;
            }
        }
        return $data;
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
     * Method-level wins over class-level, else null: the shared precedence of #[Version]/#[Sunset]/#[Role]/#[Permissions].
     * @template T of object
     * @param ReflectionClass<object> $rc
     * @param class-string<T> $attribute
     * @return T|null
     */
    private static function inherited(ReflectionClass $rc, ReflectionMethod $method, string $attribute): ?object
    {
        $attrs = $method->getAttributes($attribute);
        if ($attrs === []) {
            $attrs = self::classAttributes($rc, $attribute);
        }
        return $attrs === [] ? null : $attrs[0]->newInstance();
    }

    /**
     * The class and its parents, nearest first.
     * @param ReflectionClass<object> $rc
     * @return list<ReflectionClass<object>>
     */
    private static function lineage(ReflectionClass $rc): array
    {
        $lineage = [];
        for ($class = $rc; $class !== false; $class = $class->getParentClass()) {
            $lineage[] = $class;
        }
        return $lineage;
    }

    /**
     * $attribute on the nearest class of the lineage that declares it. PHP doesn't inherit attributes, so without this
     * an #[Authenticated] on a base controller would leave every subclass route open.
     * @template T of object
     * @param ReflectionClass<object> $rc
     * @param class-string<T> $attribute
     * @return list<\ReflectionAttribute<T>>
     */
    private static function classAttributes(ReflectionClass $rc, string $attribute): array
    {
        foreach (self::lineage($rc) as $class) {
            $attrs = $class->getAttributes($attribute);
            if ($attrs !== []) {
                return $attrs;
            }
        }
        return [];
    }

    /**
     * @param ReflectionClass<object> $rc
     * @param class-string $attribute
     */
    private static function hasEither(ReflectionClass $rc, ReflectionMethod $method, string $attribute): bool
    {
        return $method->getAttributes($attribute) !== [] || self::classAttributes($rc, $attribute) !== [];
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
     * #[Middleware(...)] entries in declaration order, each run through its MiddlewareBase::handle().
     * A class that doesn't exist or doesn't extend MiddlewareBase fails the compile: skipping it would
     * leave the route running without the check it was declared to have (often authentication).
     *
     * @param ReflectionAttribute<Middleware>[] $attributes
     * @return array<int, MiddlewareEntry>
     */
    private function collectMiddlewares(array $attributes, string $where): array
    {
        $middlewares = [];
        foreach ($attributes as $attr) {
            $class = $attr->newInstance()->class;
            if (!class_exists($class) || !self::extendsMiddlewareBase($class)) {
                throw new InvalidArgumentException("#[Middleware({$class})] on {$where}: the class must exist and extend " . MiddlewareBase::class . '.');
            }
            $middlewares[] = [
                'class' => $class,
                'method' => 'handle',
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

    /** The match pattern for a path with {placeholders}: everything else matches literally ('.' is a dot, '#' is no delimiter). */
    private static function pathRegex(string $path): string
    {
        $pieces = preg_split('#(\{\w+\})#', $path, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$path];
        $pattern = '';
        foreach ($pieces as $piece) {
            $pattern .= preg_match('#^\{(\w+)\}$#', $piece, $placeholder)
                ? "(?P<{$placeholder[1]}>[^/]+)"
                : preg_quote($piece, '#');
        }
        return "#^{$pattern}$#";
    }

    /** Whether a non-nullable #[Claims] parameter makes the route need a signed-in user. */
    private static function requiresClaims(ReflectionMethod $method): bool
    {
        foreach ($method->getParameters() as $param) {
            if (!$param->allowsNull() && $param->getAttributes(Claims::class) !== []) {
                return true;
            }
        }
        return false;
    }

    /**
     * $argPlan, unless a parameter nothing binds would get null without taking it: a route that could only ever
     * fail with a TypeError stops the boot instead.
     * @param array<int, array<string, mixed>> $argPlan
     * @return array<int, array<string, mixed>>
     */
    private static function checkedArgPlan(ReflectionMethod $method, array $argPlan): array
    {
        foreach ($method->getParameters() as $index => $param) {
            if (($argPlan[$index]['inject'] ?? null) === 'Unknown' && !$param->allowsNull()) {
                throw new InvalidArgumentException("Cannot bind \${$param->getName()} of {$method->class}::{$method->getName()}(): type {$param->getType()} is no Request, Response, scalar or backed enum, and has no #[Body], #[Query], #[Param] or plugin attribute.");
            }
        }
        return $argPlan;
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
            $plugin = $this->pluginArgPlan($param);

            $argPlan[] = match (true) {
                $type === Request::class => ['inject' => 'Request'],
                $type === Response::class => ['inject' => 'Response'],
                $body !== null && $type !== null && class_exists($type) => ['inject' => 'Body', 'class' => $type, 'validate' => $body->validate],
                $type !== null && class_exists($type) && self::attribute($param, Claims::class) !== null => ['inject' => 'Claims', 'class' => $type, 'nullable' => $param->allowsNull()],
                $body !== null && in_array($type, ['array', 'iterable'], true) && $of !== null && class_exists($of) => ['inject' => 'BodyCollection', 'class' => $of, 'validate' => $body->validate],
                $body !== null && in_array($type, ['array', 'iterable'], true) => ['inject' => 'BodyArray'],
                ($query = self::attribute($param, Query::class)) !== null => ['inject' => 'Query', 'name' => $query->name ?? $param->getName()] + self::scalarPlan($param, $type),
                ($route = self::attribute($param, Param::class)) !== null => ['inject' => 'Route', 'name' => $route->name ?? $param->getName()] + self::scalarPlan($param, $type),
                $plugin !== null => $plugin,
                in_array($type, ['string', 'int', 'float', 'bool'], true) || self::backing($type) !== null => ['inject' => 'Scalar', 'name' => $param->getName()] + self::scalarPlan($param, $type),
                default => ['inject' => 'Unknown'],
            };
        }
        return $argPlan;
    }

    /**
     * For a scalar parameter: its type, and what a missing value becomes (its default, null, or the type's empty value).
     * @return array<string, mixed>
     */
    private static function scalarPlan(ReflectionParameter $param, ?string $type): array
    {
        $backing = self::backing($type);
        if (!in_array($type, ['string', 'int', 'float', 'bool'], true) && $backing === null) {
            return [];
        }
        $plan = ['type' => $type, 'nullable' => $param->allowsNull()];
        if ($backing !== null) {
            // A backed enum: the URL's text converted to the backing type, then tryFrom()
            $plan['enum'] = $backing;
        }
        if ($param->isDefaultValueAvailable()) {
            $plan['default'] = $param->getDefaultValue();
        }
        return $plan;
    }

    /** 'int' or 'string' for a backed enum's class name, else null. */
    private static function backing(?string $type): ?string
    {
        return $type !== null && enum_exists($type) && is_subclass_of($type, \BackedEnum::class)
            ? (string) (new \ReflectionEnum($type))->getBackingType()
            : null;
    }

    /**
     * The first plugin binder that claims $param, tagged with its name for ArgumentResolver.
     * @return array<string, mixed>|null
     */
    private function pluginArgPlan(ReflectionParameter $param): ?array
    {
        foreach ($this->binders as $name => $binder) {
            $plan = $binder->plan($param);
            if ($plan !== null) {
                return ['inject' => 'plugin', 'plugin' => $name] + $plan;
            }
        }
        return null;
    }

    /**
     * Every class attribute of the lineage, a parent's only where no nearer class declares the same one.
     * @param ReflectionClass<object> $rc
     * @return list<\ReflectionAttribute<object>>
     */
    private static function lineageAttributes(ReflectionClass $rc): array
    {
        $found = [];
        $declared = [];
        foreach (self::lineage($rc) as $class) {
            $own = $class->getAttributes();
            foreach ($own as $attr) {
                if (!isset($declared[$attr->getName()])) {
                    $found[] = $attr;
                }
            }
            foreach ($own as $attr) {
                $declared[$attr->getName()] = true;
            }
        }
        return $found;
    }

    /**
     * Every attribute on every controller, property, method and parameter, as
     * plain {name, args} entries plus each parameter's type. This is what
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
            $result[$className]['__class'] = self::attributeEntries(self::lineageAttributes($rc));

            foreach ($rc->getProperties() as $prop) {
                $result[$className]['properties'][$prop->getName()] = self::attributeEntries($prop->getAttributes());
            }

            foreach ($rc->getMethods() as $method) {
                $returnType = $method->getReturnType();
                $result[$className]['methods'][$method->getName()]['__method'] = self::attributeEntries($method->getAttributes());
                $result[$className]['methods'][$method->getName()]['returnType'] = $returnType instanceof ReflectionNamedType ? $returnType->getName() : null;
                $result[$className]['methods'][$method->getName()]['returnNullable'] = $returnType === null || $returnType->allowsNull();
                $result[$className]['methods'][$method->getName()]['returnsPage'] = self::returnsPage($returnType);
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

    /** Whether a return type names a View or a Redirect (alone or in a union): an HTML page, no API operation. */
    private static function returnsPage(?\ReflectionType $type): bool
    {
        $types = $type instanceof \ReflectionUnionType ? $type->getTypes() : [$type];
        foreach ($types as $named) {
            if ($named instanceof ReflectionNamedType && (is_a($named->getName(), View::class, true) || is_a($named->getName(), Redirect::class, true))) {
                return true;
            }
        }
        return false;
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
