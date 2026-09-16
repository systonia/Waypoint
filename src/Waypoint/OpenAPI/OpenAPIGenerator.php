<?php

namespace Waypoint\OpenAPI;

use Deprecated;
use stdClass;
use Waypoint\{FileSystem, Logger, Router, Waypoint};
use Waypoint\Attributes\{Body, Ignore, Param, Query, Summary, Tags, Throws};
use Waypoint\Enums\Message;
use Waypoint\Options\{FileSystemOptions, OpenAPIOptions};
use Waypoint\Support\Arr;

/**
 * Builds an OpenAPI 3.1 document from the Router's route list and the
 * attribute cache RouteCompiler::exportAllAttributes() wrote -- no
 * controller reflection at request time; only DTO classes are reflected,
 * by SchemaBuilder.
 */
class OpenAPIGenerator
{
    private Logger $logger;
    private SchemaBuilder $schemas;

    /** @var array<class-string, mixed> See RouteCompiler::exportAllAttributes() for the shape. */
    private array $attributeCache;

    public function __construct(private Router $router)
    {
        $this->logger = new Logger();
        $this->schemas = new SchemaBuilder($this->logger);
        $this->attributeCache = (new FileSystem(Waypoint::getConfig(FileSystemOptions::class)))->loadAttributes();
    }

    /**
     * @param string|null $version Null: every unversioned route plus, per (method, path) group, only the
     *  newest #[Version]. A version string: only that version's routes (spec.vX.json).
     * @return array<string, mixed>
     */
    public function generate(?string $version = null): array
    {
        $options = Waypoint::getConfig(OpenAPIOptions::class)->toArray();
        $paths = $this->buildPaths($version);

        $components = Arr::stringKeyed($options['components'] ?? null);
        $securitySchemes = Arr::stringKeyed($components['securitySchemes'] ?? null);

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

    /** True if any compiled route carries #[Version($version)] -- so a spec.vX.json for an unknown version can 404. */
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
        foreach ($this->selectEligibleRoutes($this->router->getRoutes(), $version) as $route) {
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

            $paths[$rawPath][strtolower($method)] = $this->buildOperation($controllerClass, $methodName, $methodAttrs, $classAttrs);
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
     * [controllerClass, methodName] from a route's handlerSpec -- a plain pair, or one nested under
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
    protected function buildOperation(string $controllerClass, string $methodName, array $methodAttrs, array $classAttrs): array
    {
        [$parameters, $requestBody] = $this->extractParameters($controllerClass, $methodName);

        $responses = ['200' => ['description' => 'Successful response', 'content' => ['application/json' => ['schema' => ['type' => 'object']]]]];
        foreach ($methodAttrs as $attr) {
            if ($attr['name'] !== Throws::class) {
                continue;
            }
            $status = $attr['args']['status'] ?? null;
            $status = is_int($status) || is_string($status) ? (string) $status : '500';
            if (!isset($responses[$status])) {
                $this->schemas->ensureProblemDetails();
                $responses[$status] = [
                    'description' => $attr['args']['description'] ?? $attr['args']['exception'] ?? 'Error',
                    'content' => ['application/problem+json' => ['schema' => ['$ref' => '#/components/schemas/ProblemDetails']]],
                ];
            }
        }

        $summaryArgs = self::findAttr($methodAttrs, Summary::class) ?? [];
        $tags = [...self::tagsOf($classAttrs), ...self::tagsOf($methodAttrs)];

        return array_filter([
            'summary' => self::stringArg($summaryArgs, 0, 'text') ?? "$controllerClass->$methodName",
            'operationId' => $controllerClass . '_' . $methodName,
            'tags' => array_values(array_unique($tags)),
            'parameters' => $parameters,
            'requestBody' => $requestBody,
            'responses' => $responses,
            'deprecated' => self::findAttr([...$classAttrs, ...$methodAttrs], Deprecated::class) !== null,
        ], fn($v) => $v !== null);
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
     * placeholder-then-query binding, documented as a query parameter.
     *
     * @return array{0: list<array<string, mixed>>, 1: array<string, mixed>|null}
     */
    protected function extractParameters(string $controllerClass, string $methodName): array
    {
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
                $kind = 'query';
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
