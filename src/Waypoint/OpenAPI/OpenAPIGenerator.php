<?php

namespace Waypoint\OpenAPI;

use Deprecated;
use stdClass;
use Waypoint\{Logger, Router, Waypoint};
use Waypoint\Attributes\{Authenticated, Body, Ignore, Param, Permissions, Query, Role, Summary, Tags, Throws};
use Waypoint\Enums\Message;
use Waypoint\Exceptions\HttpException;
use Waypoint\Options\OpenAPIOptions;
use Waypoint\Plugin\OpenApiExtension;
use Waypoint\Support\Arr;

/**
 * Builds an OpenAPI 3.1 document from the Router's route list and the
 * attribute cache RouteCompiler::exportAllAttributes() wrote, with no
 * controller reflection at request time; only DTO classes are reflected,
 * by SchemaBuilder.
 */
class OpenAPIGenerator
{
    /** The scheme a guarded route requires when OpenAPIOptions configures none. */
    private const DEFAULT_SCHEME = 'bearerAuth';

    private Logger $logger;
    private SchemaBuilder $schemas;

    /** @var array<class-string, mixed> See RouteCompiler::exportAllAttributes() for the shape. */
    private array $attributeCache;

    /** @var array<string, OpenApiExtension> plugin name => extension */
    private array $extensions;

    /** @var list<string> OpenAPIOptions' security scheme names (a JWT bearer one without any): what a guarded route requires when there is no global security. */
    private array $schemeNames = [];

    /** @var list<string> ShortClass_method ids two controllers would share: those operations keep the full class name. */
    private array $ambiguousIds = [];

    /** Whether OpenAPIOptions sets a document-wide `security` that every route inherits. */
    private bool $globalSecurity = false;


    public function __construct(private Router $router)
    {
        $this->logger = new Logger();
        $this->schemas = new SchemaBuilder($this->logger);
        $this->attributeCache = $router->compiledAttributes();
        $this->extensions = $router->pluginHooks(OpenApiExtension::class);
    }

    /**
     * @param string|null $version Null: every unversioned route plus, per (method, path) group, only the
     *  newest #[Version]. A version string: only that version's routes (spec.vX.json).
     * @return array<string, mixed>
     */
    public function generate(?string $version = null): array
    {
        $options = Waypoint::getConfig(OpenAPIOptions::class)->toArray();
        $components = Arr::stringKeyed($options['components'] ?? null);
        $securitySchemes = Arr::stringKeyed($components['securitySchemes'] ?? null);
        $this->schemeNames = $securitySchemes !== [] ? array_keys($securitySchemes) : [self::DEFAULT_SCHEME];
        $this->globalSecurity = ($options['security'] ?? null) !== null;

        $paths = $this->buildPaths($version);
        if ($securitySchemes === [] && self::requiresSecurity($paths)) {
            $securitySchemes[self::DEFAULT_SCHEME] = ['type' => 'http', 'scheme' => 'bearer', 'bearerFormat' => 'JWT'];
        }

        return array_filter([
            'openapi' => '3.1.0',
            'info' => $options['info'],
            'paths' => $paths,
            'components' => [
                'schemas' => $this->schemas->schemas(),
                'securitySchemes' => $securitySchemes !== [] ? $securitySchemes : new stdClass(),
            ],
            'servers' => $options['servers'] ?? null,
            'security' => $options['security'] ?? null,
            'tags' => $options['tags'] ?? null,
            'externalDocs' => $options['externalDocs'] ?? null,
        ], fn($value) => $value !== null);
    }

    /** True if any compiled route carries #[Version($version)], so a spec.vX.json for an unknown version can 404. */
    public function hasVersion(string $version): bool
    {
        foreach ($this->router->getRoutes() as $route) {
            if (self::routeField($route, 'version') === $version) {
                return true;
            }
        }
        return false;
    }

    /** A route is a RouteSummary object in practice; a plain array is tolerated (see OpenAPIGeneratorRouteShapeTest). */
    private static function routeField(mixed $route, string $field): mixed
    {
        if (is_object($route)) {
            return $route->{$field} ?? null;
        }
        return is_array($route) ? ($route[$field] ?? null) : null;
    }

    /** @return array<string, mixed> */
    protected function buildPaths(?string $version = null): array
    {
        $paths = [];
        $routes = $this->selectEligibleRoutes($this->router->getRoutes(), $version);
        // ShortClass_method, unless two controllers of the same short name share it: then both keep their full name
        $ids = [];
        foreach ($routes as $route) {
            [$class, $method] = $this->getHandlerSpec($route) ?? ['', ''];
            $ids[self::shortName($class) . "_$method"][$class] = true;
        }
        $this->ambiguousIds = array_keys(array_filter($ids, static fn (array $classes): bool => count($classes) > 1));
        foreach ($routes as $route) {
            $handlerSpec = $this->getHandlerSpec($route);
            $method = self::routeField($route, 'method');
            $rawPath = self::routeField($route, 'rawPath');
            if ($handlerSpec === null || !is_string($method) || !is_string($rawPath) || $method === '' || $rawPath === '') {
                continue;
            }
            [$controllerClass, $methodName] = $handlerSpec;

            $classAttrs = self::attrList($this->classCacheEntry($controllerClass)['__class'] ?? null);
            $methodAttrs = self::attrList($this->methodCacheEntry($controllerClass, $methodName)['__method'] ?? null);
            if (self::findAttr([...$classAttrs, ...$methodAttrs], Ignore::class) !== null) {
                continue;
            }

            $paths[$rawPath][strtolower($method)] = $this->buildOperation($controllerClass, $methodName, $methodAttrs, $classAttrs, $rawPath);
        }

        ksort($paths);
        $order = array_flip(['get', 'post', 'put', 'patch', 'delete']);
        foreach ($paths as &$operations) {
            uksort($operations, fn(string $a, string $b): int => ($order[$a] ?? PHP_INT_MAX) <=> ($order[$b] ?? PHP_INT_MAX));
        }
        return $paths;
    }

    /**
     * @param array<int, mixed> $routes
     * @return array<int, mixed> Exact-version filter when $version is given; otherwise every
     *  unversioned route plus the newest version of each (method, unversionedPath) group.
     */
    protected function selectEligibleRoutes(array $routes, ?string $version): array
    {
        if ($version !== null) {
            return array_values(array_filter($routes, fn($route) => self::routeField($route, 'version') === $version));
        }

        $unversioned = [];
        $latestByGroup = [];
        foreach ($routes as $route) {
            $routeVersion = self::routeField($route, 'version');
            if (!is_string($routeVersion)) {
                $unversioned[] = $route;
                continue;
            }
            $method = self::routeField($route, 'method');
            $groupPath = self::routeField($route, 'unversionedPath') ?? self::routeField($route, 'rawPath');
            $key = (is_string($method) ? $method : '') . ' ' . (is_string($groupPath) ? $groupPath : '');
            $incumbentVersion = isset($latestByGroup[$key]) ? self::routeField($latestByGroup[$key], 'version') : null;
            if (!is_string($incumbentVersion) || $this->compareVersions($routeVersion, $incumbentVersion) > 0) {
                $latestByGroup[$key] = $route;
            }
        }
        return array_merge($unversioned, array_values($latestByGroup));
    }

    /** <=> on the numeric runs of two version strings, so v10 > v2. */
    protected function compareVersions(string $a, string $b): int
    {
        $partsA = $this->versionSortKey($a);
        $partsB = $this->versionSortKey($b);
        for ($i = 0, $n = max(count($partsA), count($partsB)); $i < $n; $i++) {
            $cmp = ($partsA[$i] ?? 0) <=> ($partsB[$i] ?? 0);
            if ($cmp !== 0) {
                return $cmp;
            }
        }
        return 0;
    }

    /** @return int[] 'v1.2' -> [1, 2]; no digits -> [0]. */
    protected function versionSortKey(string $version): array
    {
        preg_match_all('/\d+/', $version, $matches);
        return array_map('intval', $matches[0]) ?: [0];
    }

    /**
     * [controllerClass, methodName] from a route's handlerSpec: a plain pair, or one nested under
     * 'spec'. The class isn't verified to exist: a missing attribute-cache entry just means "no attributes".
     *
     * @return array{0: string, 1: string}|null
     */
    protected function getHandlerSpec(mixed $route): ?array
    {
        $spec = self::routeField($route, 'handlerSpec');
        if (is_array($spec) && isset($spec['spec'])) {
            $spec = $spec['spec'];
        }
        if (!is_array($spec) || !isset($spec[0], $spec[1]) || !is_string($spec[0]) || !is_string($spec[1])) {
            return null;
        }
        return [$spec[0], $spec[1]];
    }

    /** @return array<string, mixed> */
    private function classCacheEntry(string $controllerClass): array
    {
        return Arr::stringKeyed($this->attributeCache[$controllerClass] ?? null);
    }

    /** @return array<string, mixed> */
    private function methodCacheEntry(string $controllerClass, string $methodName): array
    {
        $methods = Arr::stringKeyed($this->classCacheEntry($controllerClass)['methods'] ?? null);
        return Arr::stringKeyed($methods[$methodName] ?? null);
    }

    /**
     * A cached attribute list narrowed to {name, args} entries.
     * @return list<array{name: string, args: array<int|string, mixed>}>
     */
    private static function attrList(mixed $value): array
    {
        $result = [];
        foreach (Arr::listOfStringKeyed($value) as $item) {
            $name = $item['name'] ?? '';
            $args = $item['args'] ?? [];
            $result[] = ['name' => is_string($name) ? $name : '', 'args' => is_array($args) ? $args : []];
        }
        return $result;
    }

    /**
     * The args of the first attribute named $class, or null if absent.
     * @param list<array{name: string, args: array<int|string, mixed>}> $attrs
     * @return array<int|string, mixed>|null
     */
    private static function findAttr(array $attrs, string $class): ?array
    {
        foreach ($attrs as $attr) {
            if ($attr['name'] === $class) {
                return $attr['args'];
            }
        }
        return null;
    }

    /**
     * A positional-or-named constructor argument, narrowed to string.
     * @param array<int|string, mixed> $args
     */
    private static function stringArg(array $args, int $position, string $name): ?string
    {
        $value = $args[$position] ?? $args[$name] ?? null;
        return is_string($value) ? $value : null;
    }

    /**
     * @param list<array{name: string, args: array<int|string, mixed>}> $methodAttrs
     * @param list<array{name: string, args: array<int|string, mixed>}> $classAttrs
     * @return array<string, mixed>
     */
    protected function buildOperation(string $controllerClass, string $methodName, array $methodAttrs, array $classAttrs, string $path = ''): array
    {
        [$parameters, $requestBody] = $this->extractParameters($controllerClass, $methodName, $path);

        $returnType = $this->methodCacheEntry($controllerClass, $methodName)['returnType'] ?? null;
        $content = is_string($returnType) ? $this->pluginContent($returnType) : null;
        $responses = ['200' => ['description' => 'Successful response', 'content' => $content ?? ['application/json' => ['schema' => ['type' => 'object']]]]];
        foreach ($methodAttrs as $attr) {
            if ($attr['name'] !== Throws::class) {
                continue;
            }
            $args = $attr['args'];
            $status = $args[1] ?? $args['status'] ?? null;
            $status = is_int($status) ? $status : self::defaultStatus(self::stringArg($args, 0, 'exception'));
            if (!isset($responses[$status])) {
                $this->schemas->ensureProblemDetails();
                $responses[$status] = [
                    'description' => self::stringArg($args, 2, 'description') ?? self::reason($status),
                    'content' => ['application/problem+json' => ['schema' => ['$ref' => '#/components/schemas/ProblemDetails']]],
                ];
            }
        }

        [$security, $guardResponses] = $this->guardOf($methodAttrs, $classAttrs);
        if ($requestBody !== null || array_filter($parameters, self::converted(...)) !== []) {
            $guardResponses[422] = 'Validation failed';
        }
        foreach ($guardResponses as $status => $description) {
            if (!isset($responses[$status])) {
                $this->schemas->ensureProblemDetails();
                $responses[$status] = [
                    'description' => $description,
                    'content' => ['application/problem+json' => ['schema' => ['$ref' => '#/components/schemas/ProblemDetails']]],
                ];
            }
        }
        ksort($responses);

        $summaryArgs = self::findAttr($methodAttrs, Summary::class) ?? [];
        $tags = [...self::tagsOf($classAttrs), ...self::tagsOf($methodAttrs)];

        return array_filter([
            'summary' => self::stringArg($summaryArgs, 0, 'text') ?? "$controllerClass->$methodName",
            'operationId' => self::operationId($controllerClass, $methodName, $this->ambiguousIds),
            'tags' => array_values(array_unique($tags)),
            'parameters' => $parameters,
            'requestBody' => $requestBody,
            'responses' => $responses,
            'security' => $security,
            'deprecated' => self::findAttr([...$classAttrs, ...$methodAttrs], Deprecated::class) !== null,
        ], fn($v) => $v !== null);
    }

    /**
     * The operation's `security` (null: the document's applies) and the 401/403 responses its guards can give, from
     * #[Authenticated]/#[Role]/#[Permissions] (the method's role and permissions win over the class's, as at runtime).
     * @param list<array{name: string, args: array<int|string, mixed>}> $methodAttrs
     * @param list<array{name: string, args: array<int|string, mixed>}> $classAttrs
     * @return array{0: list<array<string, list<string>>>|null, 1: array<int, string>}
     */
    private function guardOf(array $methodAttrs, array $classAttrs): array
    {
        $role = self::stringArg(self::findAttr($methodAttrs, Role::class) ?? self::findAttr($classAttrs, Role::class) ?? [], 0, 'role');
        $permissions = self::findAttr($methodAttrs, Permissions::class) ?? self::findAttr($classAttrs, Permissions::class);
        $permissions = $permissions !== null ? Arr::stringList($permissions[0] ?? $permissions['permissions'] ?? []) : null;
        $guarded = $role !== null || $permissions !== null || self::findAttr([...$classAttrs, ...$methodAttrs], Authenticated::class) !== null;
        if (!$guarded) {
            // A public route opts out of the document's security
            return [$this->globalSecurity ? [] : null, []];
        }
        $responses = [401 => 'Not authenticated'];
        if ($role !== null) {
            $responses[403] = "Requires the role {$role}";
        } elseif ($permissions !== null) {
            $responses[403] = 'Requires the permissions ' . implode(', ', $permissions);
        }
        if ($this->globalSecurity) {
            return [null, $responses];
        }
        return [array_map(static fn (string $scheme): array => [$scheme => []], $this->schemeNames), $responses];
    }

    /**
     * Whether some operation requires a security scheme of its own.
     * @param array<string, mixed> $paths
     */
    private static function requiresSecurity(array $paths): bool
    {
        foreach ($paths as $operations) {
            foreach (is_array($operations) ? $operations : [] as $operation) {
                if (is_array($operation) && ($operation['security'] ?? []) !== []) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * `ShortClass_method`; the class name in full, its backslashes as dots, where that would be ambiguous.
     * @param list<string> $ambiguous
     */
    private static function operationId(string $class, string $method, array $ambiguous): string
    {
        $short = self::shortName($class) . "_$method";
        return in_array($short, $ambiguous, true) ? str_replace('\\', '.', $class) . "_$method" : $short;
    }

    private static function shortName(string $class): string
    {
        $position = strrpos($class, '\\');
        return $position === false ? $class : substr($class, $position + 1);
    }

    /** The response description for a status without one of its own. */
    private static function reason(int $status): string
    {
        return match ($status) {
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            409 => 'Conflict',
            410 => 'Gone',
            422 => 'Unprocessable Content',
            429 => 'Too Many Requests',
            500 => 'Internal Server Error',
            503 => 'Service Unavailable',
            default => 'Error',
        };
    }

    /** What an exception class answers when it is thrown without a status: an HttpException's own, else 500. */
    private static function defaultStatus(?string $exception): int
    {
        if ($exception === null || !is_subclass_of($exception, HttpException::class)) {
            return 500;
        }
        try {
            return (new \ReflectionClass($exception))->newInstance()->getStatusCode();
        } catch (\Throwable) {
            return 500;
        }
    }

    /**
     * Whether a parameter's value is converted, so a bad one answers 422: a number, a boolean or an enum.
     * @param array<string, mixed> $parameter
     */
    private static function converted(array $parameter): bool
    {
        $schema = $parameter['schema'] ?? null;
        return is_array($schema) && (isset($schema['enum']) || in_array($schema['type'] ?? null, ['integer', 'number', 'boolean'], true));
    }

    /** @return array<string, array<string, mixed>>|null The first plugin's description of $returnType. */
    private function pluginContent(string $returnType): ?array
    {
        foreach ($this->extensions as $extension) {
            $content = $extension->describeResult($returnType);
            if ($content !== null) {
                return $content;
            }
        }
        return null;
    }

    /**
     * The first plugin's description of a parameter, or null.
     * @param list<array{name: string, args: array<int|string, mixed>}> $attributes
     * @return array{parameters?: list<array<string, mixed>>, requestBody?: array<string, mixed>}|null
     */
    private function pluginParameter(string $name, ?string $type, bool $nullable, bool $hasDefault, array $attributes): ?array
    {
        $parameter = ['name' => $name, 'type' => $type, 'nullable' => $nullable, 'hasDefault' => $hasDefault, 'attributes' => $attributes];
        foreach ($this->extensions as $extension) {
            $described = $extension->describeParameter($parameter);
            if ($described !== null) {
                return $described;
            }
        }
        return null;
    }

    /**
     * @param list<array{name: string, args: array<int|string, mixed>}> $attrs
     * @return string[]
     */
    private static function tagsOf(array $attrs): array
    {
        $args = self::findAttr($attrs, Tags::class);
        if ($args === null) {
            return [];
        }
        $tags = $args[0] ?? $args['tags'] ?? [];
        return Arr::stringList(is_array($tags) ? $tags : [$tags]);
    }

    /**
     * `parameters` and `requestBody` for one operation, from the cached parameter metadata
     * (attributes, PHP type, nullability, default). An unattributed scalar is Router's implicit
     * placeholder-then-query binding: a path parameter when $path has a placeholder of its name, otherwise a query parameter.
     *
     * @return array{0: list<array<string, mixed>>, 1: array<string, mixed>|null}
     */
    protected function extractParameters(string $controllerClass, string $methodName, string $path = ''): array
    {
        preg_match_all('#\{(\w+)\}#', $path, $placeholders);
        $params = [];
        $requestBody = null;

        foreach (Arr::stringKeyed($this->methodCacheEntry($controllerClass, $methodName)['parameters'] ?? null) as $paramName => $meta) {
            $metaArr = Arr::stringKeyed($meta);
            // An older cache stored a bare attribute list with no type/nullable/hasDefault.
            $attributes = self::attrList(array_key_exists('attributes', $metaArr) ? $metaArr['attributes'] : $meta);
            $type = $metaArr['type'] ?? null;
            $type = is_string($type) ? $type : null;
            $nullable = (bool) ($metaArr['nullable'] ?? false);
            $hasDefault = (bool) ($metaArr['hasDefault'] ?? false);

            $described = $this->pluginParameter($paramName, $type, $nullable, $hasDefault, $attributes);
            if ($described !== null) {
                $params = [...$params, ...($described['parameters'] ?? [])];
                $requestBody = $described['requestBody'] ?? $requestBody;
                continue;
            }

            $kind = null;
            $bindName = $paramName;
            $bodyOf = null;
            foreach ($attributes as ['name' => $name, 'args' => $args]) {
                if ($name === Param::class || $name === Query::class) {
                    $kind = $name === Param::class ? 'path' : 'query';
                    $bindName = self::stringArg($args, 0, 'name') ?? $paramName;
                } elseif ($name === Body::class) {
                    $kind = 'body';
                    $bodyOf = self::stringArg($args, 1, 'of');
                }
            }
            if ($kind === null && SchemaBuilder::isScalar($type) && $type !== 'number') {
                $kind = in_array($paramName, $placeholders[1], true) ? 'path' : 'query';
            }

            if ($kind === 'path' || $kind === 'query') {
                $params[] = [
                    'name' => $bindName,
                    'in' => $kind,
                    'required' => $kind === 'path' || (!$nullable && !$hasDefault),
                    'schema' => SchemaBuilder::parameterSchema($type),
                ];
            } elseif ($kind === 'body') {
                $isCollection = in_array($type, ['array', 'iterable'], true) && $bodyOf !== null;
                $dto = $isCollection ? $bodyOf : $type;
                if ($dto === null || !class_exists($dto)) {
                    $this->logger->warning(Message::GeneratorPropertyDoesNotExist2->interpolate(type: $dto ?? 'mixed', method: "$controllerClass::$methodName"));
                    continue;
                }
                $ref = ['$ref' => $this->schemas->refFor($dto)];
                $requestBody = [
                    'required' => !$nullable,
                    'content' => ['application/json' => ['schema' => $isCollection ? ['type' => 'array', 'items' => $ref] : $ref]],
                ];
            }
        }

        return [$params, $requestBody];
    }
}
