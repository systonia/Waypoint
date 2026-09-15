<?php

namespace Waypoint\OpenAPI;

use RuntimeException;
use ReflectionClass;
use ReflectionNamedType;
use Deprecated;
use stdClass;

use Waypoint\{Router, FileSystem, Logger, Waypoint};
use Waypoint\Options\{OpenAPIOptions, FileSystemOptions};
use Waypoint\Enums\Message;
use Waypoint\Attributes\{Ignore, Throws, Summary, Tags, Param, Query, Body, Schema, Property};

/**
 * OpenAPI Generator (cache-driven, zero-reflection hot path)
 */
class OpenAPIGenerator
{
    private Router $router;
    private Logger $logger;

    /** @var array<class-string, mixed> See RouteCompiler::exportAllAttributes() for the shape. */
    private array $attributeCache;

    /** @var array<string, bool> Schema name => already generated, guarding against infinite recursion on a self-/mutually-referencing model. */
    private array $processedModels = [];

    /** @var array{schemas: array<string, mixed>, securitySchemes: array<string, mixed>|stdClass} */
    private array $components = [
        'schemas' => [],
        'securitySchemes' => [],
    ];

    public function __construct(Router $router)
    {
        $this->router = $router;
        $this->logger = new Logger();
        $this->attributeCache = (new FileSystem(Waypoint::getConfig(FileSystemOptions::class)))->loadAttributes();

        if (empty($this->components['securitySchemes'])) {
            $this->components['securitySchemes'] = new \stdClass();
        }
    }

    /**
     * @param string|null $version Omitted (or null): the combined
     *  "current" spec -- every unversioned route, plus, for each
     *  #[Version]-bearing route that has more than one version sharing the
     *  same underlying path (see selectEligibleRoutes()), only its
     *  highest/newest version. Given an exact version string (e.g. 'v1'):
     *  only that version's own routes, for spec.vX.json. See
     *  OpenAPIController.
     *
     * @return array<string, mixed>
     */
    public function generate(?string $version = null): array
    {
        // 'info' is required by the OpenAPI spec; OpenAPIOptions already
        // builds it (plus servers/tags/security/externalDocs) from whatever
        // the app configured via App::configure(OpenAPIOptions), so pull
        // that in instead of leaving these as permanently-empty stubs.
        $options = Waypoint::getConfig(OpenAPIOptions::class)->toArray();

        // toArray() nests configured security schemes under
        // components.securitySchemes, but $this->components (schemas built
        // from #[Body] DTOs, plus its own securitySchemes default) is what
        // actually gets returned below -- without this, configuring
        // OpenAPIOptions::$securitySchemes had no effect at all.
        $components = $options['components'] ?? null;
        $securitySchemes = is_array($components) ? ($components['securitySchemes'] ?? null) : null;
        if (is_array($securitySchemes) && !empty($securitySchemes)) {
            $this->components['securitySchemes'] = $this->toStringKeyedArray($securitySchemes);
        }

        return array_filter([
            'openapi' => '3.1.0',
            'info' => $options['info'],
            'paths' => $this->buildPaths($version),
            'components' => $this->components,
            'servers' => $options['servers'] ?? null,
            'security' => $options['security'] ?? null,
            'tags' => $options['tags'] ?? null,
            'externalDocs' => $options['externalDocs'] ?? null,
        ], fn($value) => $value !== null);
    }

    /** True if at least one compiled route carries the given #[Version] -- OpenAPIController uses this to 404 a spec.vX.json for a version that doesn't exist rather than silently returning an empty spec. */
    public function hasVersion(string $version): bool
    {
        foreach ($this->router->getRoutes() as $route) {
            if (($route->version ?? null) === $version) {
                return true;
            }
        }
        return false;
    }

    /**
     * Reads one field off a route -- Router::getRoutes()' RouteSummary in
     * practice, but see this class's own tolerance for other shapes (e.g.
     * OpenAPIGeneratorRouteShapeTest's stubbed array-shaped routes).
     */
    private static function routeField(mixed $route, string $field): mixed
    {
        if (is_object($route)) {
            return $route->{$field} ?? null;
        }
        if (is_array($route)) {
            return $route[$field] ?? null;
        }
        // @codeCoverageIgnoreStart
        // Router::getRoutes() only ever produces objects, and even
        // OpenAPIGeneratorRouteShapeTest's stubbed routes are always
        // either objects or arrays.
        return null;
        // @codeCoverageIgnoreEnd
    }

    /** @return array<string, mixed> */
    protected function buildPaths(?string $version = null): array
    {
        $paths = [];

        foreach ($this->selectEligibleRoutes($this->router->getRoutes(), $version) as $route) {
            $handlerSpec = $this->getHandlerSpec($route);
            if (!$handlerSpec) {
                continue;
            }

            [$controllerClass, $methodName] = $handlerSpec;

            $methodRef = self::routeField($route, 'method');
            $rawPath = self::routeField($route, 'rawPath');
            if (!is_string($methodRef) || !is_string($rawPath) || $methodRef === '' || $rawPath === '') {
                continue;
            }

            // Use attribute cache instead of reflection
            $classAttrs = $this->attrList($this->classCacheEntry($controllerClass)['__class'] ?? null);
            $methodAttrs = $this->attrList($this->methodCacheEntry($controllerClass, $methodName)['__method'] ?? null);

            if ($this->isIgnored($classAttrs, $methodAttrs)) {
                continue;
            }

            $httpMethod = strtolower($methodRef);

            $operation = $this->buildOperation($controllerClass, $methodName, $methodAttrs, $classAttrs);

            $paths[$rawPath][$httpMethod] = $operation;
        }

        ksort($paths);

        $httpOrder = ['get', 'post', 'put', 'patch', 'delete'];
        foreach ($paths as &$methods) {
            uksort($methods, function ($a, $b) use ($httpOrder) {
                $posA = array_search($a, $httpOrder);
                $posB = array_search($b, $httpOrder);
                $posA = $posA === false ? PHP_INT_MAX : $posA;
                $posB = $posB === false ? PHP_INT_MAX : $posB;
                return $posA <=> $posB;
            });
        }
        return $paths;
    }

    /**
     * @param array<int, mixed> $routes Router::getRoutes()' output -- in
     *  practice always a list of plain objects, but typed as loosely as
     *  Router::getRoutes() itself declares (not narrowed to object[]),
     *  since buildPaths()/getHandlerSpec() deliberately also tolerate an
     *  array-shaped route (see their own doc); property access below is
     *  always through `??`, so an array-shaped $route just falls into the
     *  $unversioned bucket rather than erroring.
     * @param string|null $version See generate()'s own $version doc --
     *  exact-match filter when given, "latest version per group" dedup
     *  when null.
     * @return array<int, mixed>
     */
    protected function selectEligibleRoutes(array $routes, ?string $version): array
    {
        if ($version !== null) {
            return array_values(array_filter(
                $routes,
                fn($route) => self::routeField($route, 'version') === $version
            ));
        }

        $unversioned = [];
        $latestByGroup = []; // "$method $unversionedPath" => the route object currently winning that group

        foreach ($routes as $route) {
            $routeVersion = self::routeField($route, 'version');
            if (!is_string($routeVersion)) {
                $unversioned[] = $route;
                continue;
            }

            // Groups two routes as "the same route, different versions"
            // purely by (HTTP method, pre-version-prefix path) -- their
            // actual rawPath differs (that's the whole point of URI
            // versioning), so rawPath itself can never be the group key.
            $method = self::routeField($route, 'method');
            $unversionedPath = self::routeField($route, 'unversionedPath') ?? self::routeField($route, 'rawPath');
            $key = (is_string($method) ? $method : '') . ' ' . (is_string($unversionedPath) ? $unversionedPath : '');
            $incumbent = $latestByGroup[$key] ?? null;
            $incumbentVersion = $incumbent !== null ? self::routeField($incumbent, 'version') : null;
            if ($incumbent === null || (is_string($incumbentVersion) && $this->compareVersions($routeVersion, $incumbentVersion) > 0)) {
                $latestByGroup[$key] = $route;
            }
        }

        return array_merge($unversioned, array_values($latestByGroup));
    }

    /** <=> for two version strings, comparing them semantically (v1 < v2 < v10) rather than lexicographically ('10' < '2' as plain strings). */
    protected function compareVersions(string $a, string $b): int
    {
        $partsA = $this->versionSortKey($a);
        $partsB = $this->versionSortKey($b);

        foreach (range(0, max(count($partsA), count($partsB)) - 1) as $i) {
            $cmp = ($partsA[$i] ?? 0) <=> ($partsB[$i] ?? 0);
            if ($cmp !== 0) {
                return $cmp;
            }
        }
        return 0;
    }

    /**
     * Every run of digits in $version, as ints -- 'v2' -> [2], 'v10' -> [10], 'v1.2' -> [1, 2].
     * @return int[]
     */
    protected function versionSortKey(string $version): array
    {
        preg_match_all('/\d+/', $version, $matches);
        return array_map('intval', $matches[0]) ?: [0];
    }

    /**
     * [controller-class-name, method-name] -- the class name is not
     * verified to actually exist here (see extractParameters()/buildPaths()'s
     * own attributeCache[...] lookups, which already degrade to "no
     * attributes" for one that doesn't); that's what lets
     * OpenAPIGeneratorRouteShapeTest exercise this purely off a stubbed
     * Router without real controller classes.
     *
     * @return array{0: string, 1: string}|null
     */
    protected function getHandlerSpec(mixed $route): ?array
    {
        // Handles both object and array representations
        $handlerSpec = is_object($route) ? ($route->handlerSpec ?? null) : null;
        if (is_object($route) && is_array($handlerSpec)) {
            if (isset($handlerSpec[0], $handlerSpec[1])) {
                return self::toHandlerSpecPair($handlerSpec);
            } elseif (isset($handlerSpec['spec'])) {
                return self::toHandlerSpecPair($handlerSpec['spec']);
            }
        } elseif (is_array($route)) {
            $nested = $route['handlerSpec'] ?? null;
            $spec = is_array($nested) ? ($nested['spec'] ?? null) : null;
            if ($spec !== null) {
                return self::toHandlerSpecPair($spec);
            }
        }
        return null;
    }

    /**
     * Narrows a raw [class, method] pair (from an attribute cache entry or
     * a route's handlerSpec -- both fundamentally untyped) to the precise
     * shape callers need, or null if it doesn't actually look like one.
     *
     * @return array{0: string, 1: string}|null
     */
    private static function toHandlerSpecPair(mixed $spec): ?array
    {
        if (!is_array($spec) || !isset($spec[0], $spec[1])) {
            // @codeCoverageIgnoreStart
            // getHandlerSpec()'s own isset($handlerSpec[0], $handlerSpec[1])
            // check already filters this out before calling here in the
            // direct-pair case; only the nested-'spec' case reaches this
            // helper without that pre-check, and every test/real route
            // that takes that path already provides a well-formed pair.
            return null;
            // @codeCoverageIgnoreEnd
        }
        $class = $spec[0];
        $method = $spec[1];
        if (!is_string($class) || !is_string($method)) {
            // @codeCoverageIgnoreStart
            // No test/real route provides a non-string class/method.
            return null;
            // @codeCoverageIgnoreEnd
        }
        return [$class, $method];
    }

    /**
     * $this->attributeCache[$controllerClass] narrowed to a plain
     * string-keyed array -- the cache entry is `mixed` (see
     * $attributeCache's own docblock: it's a `require`d file this same
     * class wrote via var_export(), but PHPStan can't trust that
     * statically), and $controllerClass itself may not even be a key in
     * it (a stale cache, or -- see getHandlerSpec()'s doc -- a
     * test-stubbed route naming a class that was never compiled).
     *
     * @return array<string, mixed>
     */
    private function classCacheEntry(string $controllerClass): array
    {
        $entry = $this->attributeCache[$controllerClass] ?? null;
        return $this->toStringKeyedArray($entry);
    }

    /**
     * Same reasoning as classCacheEntry(), one level deeper:
     * classCacheEntry($controllerClass)['methods'][$methodName].
     *
     * @return array<string, mixed>
     */
    private function methodCacheEntry(string $controllerClass, string $methodName): array
    {
        $methods = $this->classCacheEntry($controllerClass)['methods'] ?? null;
        if (!is_array($methods)) {
            return [];
        }
        return $this->toStringKeyedArray($methods[$methodName] ?? null);
    }

    /** @return array<string, mixed> */
    private function toStringKeyedArray(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
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
     * Narrows an arbitrary attribute-cache list (see classCacheEntry()'s
     * doc on why it's untyped) to the precise per-entry shape every
     * consumer below actually needs.
     *
     * @return array<int, array{name?: string, args?: array<int|string, mixed>}>
     */
    private function attrList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }
        $result = [];
        foreach ($value as $item) {
            if (!is_array($item)) {
                // @codeCoverageIgnoreStart
                // Every real attribute-cache entry (see
                // RouteCompiler::exportAllAttributes()) is a well-formed
                // {name, args} array; this only guards a hand-corrupted
                // attributes.php cache file.
                continue;
                // @codeCoverageIgnoreEnd
            }
            $entry = [];
            $name = $item['name'] ?? null;
            if (is_string($name)) {
                $entry['name'] = $name;
            }
            $args = $item['args'] ?? null;
            if (is_array($args)) {
                $entry['args'] = $this->toIntOrStringKeyedArray($args);
            }
            $result[] = $entry;
        }
        return $result;
    }

    /** @return array<int|string, mixed> */
    private function toIntOrStringKeyedArray(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    /**
     * @param array<int, array{name?: string, args?: array<int|string, mixed>}> $classAttrs
     * @param array<int, array{name?: string, args?: array<int|string, mixed>}> $methodAttrs
     */
    protected function isIgnored(array $classAttrs, array $methodAttrs): bool
    {
        foreach (array_merge($classAttrs, $methodAttrs) as $attr) {
            if (($attr['name'] ?? null) === Ignore::class) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array<int, array{name?: string, args?: array<int|string, mixed>}> $methodAttrs
     * @param array<int, array{name?: string, args?: array<int|string, mixed>}> $classAttrs
     * @return array<string, mixed>
     */
    protected function buildOperation(string $controllerClass, string $methodName, array $methodAttrs, array $classAttrs): array
    {
        // Parameters and requestBody
        [$parameters, $requestBody, $schemas] = $this->extractParameters($controllerClass, $methodName);

        foreach ($schemas as $name => $fqcn) {
            if (!isset($this->processedModels[$name]) && class_exists($fqcn)) {
                // Reserve the slot before recursing: generateModelSchema()
                // may itself add $name back into $schemas via a self- or
                // mutually-referencing property, and this is what stops
                // that from recursing forever.
                $this->processedModels[$name] = true;
                $this->components['schemas'][$name] = $this->generateModelSchema($fqcn);
            }
        }

        $responses = $this->getResponseSchemas($controllerClass, $methodName);

        // Throws: error responses
        foreach ($methodAttrs as $attr) {
            if (($attr['name'] ?? null) === Throws::class) {
                $throws = $attr['args'] ?? [];
                $rawStatusCode = $throws['status'] ?? null;
                // Every real #[Throws(status: ...)] in this codebase uses
                // an int literal (see DocumentedController's fixture);
                // the string/default arms only guard a status written as
                // a string, or omitted entirely.
                $statusCode = match (true) {
                    // @codeCoverageIgnoreStart
                    is_string($rawStatusCode) => $rawStatusCode,
                    // @codeCoverageIgnoreEnd
                    is_int($rawStatusCode) => (string) $rawStatusCode,
                    // @codeCoverageIgnoreStart
                    default => '500',
                    // @codeCoverageIgnoreEnd
                };
                $desc = $throws['description'] ?? $throws['exception'] ?? "Error";
                if (!isset($responses[$statusCode])) {
                    $responses[$statusCode] = [
                        'description' => $desc,
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'error' => ['type' => 'string'],
                                    ],
                                    'required' => ['error'],
                                ],
                            ],
                        ],
                    ];
                }
            }
        }

        // Summary
        $summary = $this->extractSummary($methodAttrs, $controllerClass, $methodName);

        // Tags (from method/class)
        $tags = array_merge(
            $this->extractTags($classAttrs),
            $this->extractTags($methodAttrs)
        );
        $tags = array_values(array_unique($tags));

        $deprecated = $this->isDeprecated($classAttrs, $methodAttrs);

        $operation = array_filter([
            'summary' => $summary,
            'operationId' => $controllerClass . '_' . $methodName,
            'tags' => $tags,
            'parameters' => $parameters ?: [],
            'requestBody' => $requestBody,
            'responses' => $responses ?: [],
            'deprecated' => $deprecated,
            'security' => null,
            'externalDocs' => null,
        ], function ($v) {
            return $v !== null;
        });

        return $operation;
    }

    /**
     * Builds the `parameters` list and (if the method takes a #[Body] DTO)
     * the `requestBody` schema for one operation, from the compiled
     * attribute cache -- each parameter's real PHP type (captured by
     * Router::exportAllAttributes()) drives the OpenAPI schema type, rather
     * than every parameter being documented as a plain string.
     *
     * @return array{0: array<int, array<string, mixed>>, 1: array<string, mixed>|null, 2: array<string, class-string>} [parameters, requestBody, schemas]
     */
    protected function extractParameters(string $controllerClass, string $methodName): array
    {
        $params = [];
        $requestBody = null;
        $schemas = [];

        $paramsMeta = $this->methodCacheEntry($controllerClass, $methodName)['parameters'] ?? null;
        $paramsMeta = $this->toStringKeyedArray($paramsMeta);

        foreach ($paramsMeta as $paramName => $meta) {
            // Tolerate the older cache shape (a bare list of attributes,
            // with no type/nullable/hasDefault) so a stale on-disk cache
            // degrades to "treat as string" instead of fataling.
            $metaArr = $this->toStringKeyedArray($meta);
            $rawAttributes = array_key_exists('attributes', $metaArr) ? $metaArr['attributes'] : $meta;
            $attributes = $this->attrList($rawAttributes);
            $type = $metaArr['type'] ?? null;
            $type = is_string($type) ? $type : null;
            $nullable = (bool) ($metaArr['nullable'] ?? false);
            $hasDefault = (bool) ($metaArr['hasDefault'] ?? false);

            $kind = null; // 'path' | 'query' | 'body'
            $bindName = $paramName;
            $bodyOf = null; // element DTO class, for an array/collection #[Body(of: ...)]
            foreach ($attributes as $attr) {
                $name = $attr['name'] ?? null;
                $args = $attr['args'] ?? [];
                if ($name === Param::class) {
                    $kind = 'path';
                    $bindName = self::firstStringArg($args, 0, 'name') ?? $paramName;
                } elseif ($name === Query::class) {
                    $kind = 'query';
                    $bindName = self::firstStringArg($args, 0, 'name') ?? $paramName;
                } elseif ($name === Body::class) {
                    $kind = 'body';
                    $bodyOf = self::firstStringArg($args, 1, 'of');
                }
            }

            // An unattributed scalar parameter is Router's implicit
            // "Scalar" binding, which tries the route placeholder first and
            // falls back to the query string -- documented here as a query
            // parameter, its fallback source.
            if ($kind === null && $this->isScalarType($type)) {
                $kind = 'query';
            }

            if ($kind === 'path') {
                $params[] = [
                    'name' => $bindName,
                    'in' => 'path',
                    'required' => true,
                    'schema' => $this->schemaForType($type),
                ];
            } elseif ($kind === 'query') {
                $params[] = [
                    'name' => $bindName,
                    'in' => 'query',
                    'required' => !$nullable && !$hasDefault,
                    'schema' => $this->schemaForType($type),
                ];
            } elseif ($kind === 'body' && $type !== null && in_array($type, ['array', 'iterable'], true) && $bodyOf) {
                if (!class_exists($bodyOf)) {
                    $this->logger->warning(Message::GeneratorPropertyDoesNotExist2->interpolate(
                        type: $bodyOf,
                        method: "$controllerClass::$methodName"
                    ));
                    continue;
                }
                $short = $this->schemaNameFor($bodyOf);
                $schemas[$short] = $bodyOf;
                $requestBody = [
                    'required' => !$nullable,
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'array',
                                'items' => ['$ref' => "#/components/schemas/$short"],
                            ],
                        ],
                    ],
                ];
            } elseif ($kind === 'body') {
                if (!$type || !class_exists($type)) {
                    $this->logger->warning(Message::GeneratorPropertyDoesNotExist2->interpolate(
                        type: $type ?? 'mixed',
                        method: "$controllerClass::$methodName"
                    ));
                    continue;
                }
                $short = $this->schemaNameFor($type);
                $schemas[$short] = $type;
                $requestBody = [
                    'required' => !$nullable,
                    'content' => [
                        'application/json' => ['schema' => ['$ref' => "#/components/schemas/$short"]],
                    ],
                ];
            }
        }

        return [$params, $requestBody, $schemas];
    }

    /**
     * `$args[$posKey] ?? $args[$namedKey] ?? null`, narrowed to string --
     * an attribute's positional/named constructor arg, as captured by
     * RouteCompiler::exportAllAttributes() (see AttributeEntry's own doc).
     *
     * @param array<int|string, mixed> $args
     */
    private static function firstStringArg(array $args, int $posKey, string $namedKey): ?string
    {
        $value = $args[$posKey] ?? $args[$namedKey] ?? null;
        return is_string($value) ? $value : null;
    }

    private function isScalarType(?string $type): bool
    {
        return $type !== null && in_array($type, ['string', 'int', 'integer', 'float', 'bool', 'boolean'], true);
    }

    /**
     * Maps a PHP type name to an OpenAPI schema for a parameter.
     * @return array<string, mixed>|stdClass
     */
    protected function schemaForType(?string $type): array|stdClass
    {
        if ($type === null) {
            return ['type' => 'string'];
        }
        if ($this->isScalarType($type) || $type === 'number') {
            return ['type' => $this->mapType($type)];
        }
        if ($type === 'array' || $type === 'iterable') {
            return ['type' => 'array'];
        }
        // mixed/object/callable/self/static/null/class-or-interface names
        // that aren't meant to be inlined here: no useful constraint to add.
        return new stdClass();
    }

    /**
     * The schema key/$ref name for a DTO class: its #[Schema(name: ...)]
     * override, or its short class name.
     *
     * @param class-string $fqcn
     */
    protected function schemaNameFor(string $fqcn): string
    {
        foreach ((new ReflectionClass($fqcn))->getAttributes(Schema::class) as $attr) {
            $name = $attr->newInstance()->name;
            if ($name) {
                return $name;
            }
        }
        return (new ReflectionClass($fqcn))->getShortName();
    }

    /**
     * @return array<int, mixed> Keyed by HTTP status code -- '200' as a
     *  literal array key is auto-coerced to the int 200 by PHP itself, not
     *  a string.
     */
    protected function getResponseSchemas(string $controllerClass, string $methodName): array
    {
        $responses = [];
        // Could add return type info to attributes cache at build-time for even less reflection
        $schema = ['type' => 'object'];
        $responses['200'] = [
            'description' => 'Successful response',
            'content' => ['application/json' => ['schema' => $schema]],
        ];
        return $responses;
    }

    /** @return array<string, mixed> */
    protected function generateModelSchema(string $fqcn): array
    {
        if (!$fqcn || !class_exists($fqcn)) {
            throw new RuntimeException(Message::GeneratorClassDoesNotExist->interpolate(fqcn: $fqcn));
        }

        $rc = new ReflectionClass($fqcn);
        $schema = ['type' => 'object', 'properties' => [], 'required' => []];

        foreach ($rc->getProperties() as $prop) {
            if (!$prop->isPublic())
                continue;
            $name = $prop->getName();
            $type = $prop->getType();

            if ($type instanceof ReflectionNamedType) {
                $typeName = $type->getName();
                $nullable = $type->allowsNull();
            } else {
                // No type at all, or a union/intersection type OpenAPI has
                // no single slot for -- fall back to "any", but say so.
                $this->logger->warning(Message::GeneratorPropertyHasNoType->interpolate(name: $name, rc: $fqcn));
                $typeName = null;
                $nullable = true;
            }

            $propertySchema = $this->schemaForProperty($typeName, $name, $fqcn);
            $this->applyPropertyAnnotation($prop, $propertySchema);
            $schema['properties'][$name] = $propertySchema;

            // Nullability is the only reliable "this may be omitted" signal
            // available: PHP-level default values aren't a safe proxy for
            // it here, since Waypoint's own DTO convention hydrates every
            // property from an array and so typically gives every one of
            // them *some* type-safe default regardless of whether it's
            // actually optional from the API's point of view.
            if (!$nullable) {
                $schema['required'][] = $name;
            }
        }

        if (empty($schema['required'])) {
            unset($schema['required']);
        }

        return $schema;
    }

    /**
     * Builds the (pre-annotation) schema for one model property, given its resolved type name.
     * @return array<string, mixed>
     */
    private function schemaForProperty(?string $typeName, string $propertyName, string $ownerFqcn): array
    {
        if ($typeName === null) {
            return [];
        }
        if ($this->isScalarType($typeName) || $typeName === 'number') {
            return ['type' => $this->mapType($typeName)];
        }
        if ($typeName === 'array' || $typeName === 'iterable') {
            return ['type' => 'array'];
        }
        if (in_array($typeName, ['mixed', 'object', 'callable', 'self', 'static', 'null'], true)) {
            return [];
        }
        if (!class_exists($typeName) && !interface_exists($typeName) && !enum_exists($typeName)) {
            $this->logger->warning(Message::GeneratorPropertyDoesNotExist->interpolate(
                property: $typeName,
                name: $propertyName,
                rc: $ownerFqcn
            ));
            return [];
        }

        $short = $this->schemaNameFor($typeName);
        if (!isset($this->processedModels[$short])) {
            // Reserve before recursing -- see the identical comment on the
            // top-level loop in buildOperation() for why this order matters.
            $this->processedModels[$short] = true;
            $this->components['schemas'][$short] = $this->generateModelSchema($typeName);
        }
        return ['$ref' => "#/components/schemas/$short"];
    }

    /**
     * Merges an optional #[Property(...)] attribute's description/format/example/deprecated into a property's schema.
     * @param array<string, mixed> $schema
     */
    private function applyPropertyAnnotation(\ReflectionProperty $prop, array &$schema): void
    {
        $attrs = $prop->getAttributes(Property::class);
        if (!$attrs) {
            return;
        }
        /** @var Property $annotation */
        $annotation = $attrs[0]->newInstance();

        if ($annotation->description !== null) {
            $schema['description'] = $annotation->description;
        }
        if ($annotation->format !== null) {
            $schema['format'] = $annotation->format;
        }
        if ($annotation->example !== null) {
            $schema['example'] = $annotation->example;
        }
        if ($annotation->deprecated) {
            $schema['deprecated'] = true;
        }
    }

    protected function mapType(string $phpType): string
    {
        return match ($phpType) {
            'int', 'integer' => 'integer',
            'float', 'double', 'number' => 'number',
            'bool', 'boolean' => 'boolean',
            default => 'string',
        };
    }

    /**
     * @param array<int, array{name?: string, args?: array<int|string, mixed>}> $attrs
     * @return string[]
     */
    protected function extractTags(array $attrs): array
    {
        foreach ($attrs as $attr) {
            if (($attr['name'] ?? null) === Tags::class) {
                // Positional
                if (isset($attr['args'][0])) {
                    return self::toStringArray($attr['args'][0]);
                }
                // Named
                if (isset($attr['args']['tags'])) {
                    return self::toStringArray($attr['args']['tags']);
                }
            }
        }
        return [];
    }

    /** @return string[] */
    private static function toStringArray(mixed $value): array
    {
        $items = is_array($value) ? $value : [$value];
        return array_values(array_filter($items, 'is_string'));
    }

    /** @param array<int, array{name?: string, args?: array<int|string, mixed>}> $methodAttrs */
    protected function extractSummary(array $methodAttrs, string $controllerClass, string $methodName): string
    {
        foreach ($methodAttrs as $attr) {
            if (($attr['name'] ?? null) === Summary::class) {
                // Positional
                $positional = $attr['args'][0] ?? null;
                if (is_string($positional)) {
                    return $positional;
                }
                // Named
                $named = $attr['args']['text'] ?? null;
                if (is_string($named)) {
                    return $named;
                }
            }
        }
        // Default fallback
        return "$controllerClass->$methodName";
    }

    /**
     * True if PHP's native #[\Deprecated] (8.4+) is present at either level -- RouteCompiler applies the same "either level counts" rule for the Deprecation response header (Router::dispatch()).
     * @param array<int, array{name?: string, args?: array<int|string, mixed>}> $classAttrs
     * @param array<int, array{name?: string, args?: array<int|string, mixed>}> $methodAttrs
     */
    protected function isDeprecated(array $classAttrs, array $methodAttrs): bool
    {
        foreach ([...$classAttrs, ...$methodAttrs] as $attr) {
            if (($attr['name'] ?? null) === Deprecated::class) {
                return true;
            }
        }
        return false;
    }
}
