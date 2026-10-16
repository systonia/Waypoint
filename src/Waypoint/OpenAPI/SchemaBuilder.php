<?php

namespace Waypoint\OpenAPI;

use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use RuntimeException;
use stdClass;
use Waypoint\Attributes\{Property, Schema};
use Waypoint\Enums\Message;
use Waypoint\Logger;
use Waypoint\Support\DocType;

/**
 * Builds and collects the `components.schemas` of a spec: one object schema
 * per DTO class (public properties, typed; nested DTOs by $ref, recursion-
 * safe; enums inline with their values; lists from `@var list<X>`), plus the shared RFC 9457 ProblemDetails schema. Also owns the
 * PHP-type -> OpenAPI-type mapping parameters use.
 */
final class SchemaBuilder
{
    private const SCALARS = ['string', 'int', 'integer', 'float', 'bool', 'boolean'];

    /** @var array<string, mixed> name => schema, in registration order */
    private array $schemas = [];

    /** @var array<string, true> Reserved before generating, so a self-/mutually-referencing model terminates. */
    private array $reserved = [];

    /** @var array<class-string, string> */
    private array $names = [];

    public function __construct(private Logger $logger)
    {
    }

    /** @return array<string, mixed> */
    public function schemas(): array
    {
        return $this->schemas;
    }

    /**
     * Registers $fqcn's schema (once) and returns its "#/components/schemas/..." reference.
     * @param class-string $fqcn
     */
    public function refFor(string $fqcn): string
    {
        $name = $this->nameFor($fqcn);
        if (!isset($this->reserved[$name])) {
            $this->reserved[$name] = true;
            $this->schemas[$name] = $this->generateModelSchema($fqcn);
        }
        return "#/components/schemas/$name";
    }

    /**
     * #[Schema(name: ...)] or the short class name.
     * @param class-string $fqcn
     */
    public function nameFor(string $fqcn): string
    {
        if (!isset($this->names[$fqcn])) {
            $rc = new ReflectionClass($fqcn);
            $override = ($rc->getAttributes(Schema::class)[0] ?? null)?->newInstance()->name;
            $this->names[$fqcn] = $override !== null && $override !== '' ? $override : $rc->getShortName();
        }
        return $this->names[$fqcn];
    }

    /** Registers the shared RFC 9457 schema every #[Throws] response $refs. */
    public function ensureProblemDetails(): void
    {
        $this->schemas['ProblemDetails'] ??= [
            'type' => 'object',
            'properties' => [
                'type' => ['type' => 'string', 'format' => 'uri-reference'],
                'title' => ['type' => 'string'],
                'status' => ['type' => 'integer'],
                'detail' => ['type' => 'string'],
                'instance' => ['type' => 'string', 'format' => 'uri-reference'],
            ],
            'required' => ['type', 'title', 'status'],
        ];
    }

    /**
     * An object schema from $fqcn's public properties. A property is required when it has no default and is not
     * nullable: FromArray reports exactly those as missing. A nullable one allows null (`type: [x, "null"]`), and one
     * the request cannot set (readonly, protected(set), private(set)) is `readOnly`, as FromArray never fills it.
     *
     * @return array<string, mixed>
     */
    public function generateModelSchema(string $fqcn): array
    {
        if ($fqcn === '' || !class_exists($fqcn)) {
            throw new RuntimeException(Message::GeneratorClassDoesNotExist->interpolate(fqcn: $fqcn));
        }

        $properties = [];
        $required = [];
        foreach ((new ReflectionClass($fqcn))->getProperties(ReflectionProperty::IS_PUBLIC) as $prop) {
            if ($prop->isVirtual() || $prop->isStatic()) {
                continue; // FromArray's $errors/$isValid are computed, never part of the payload
            }
            $name = $prop->getName();
            $type = $prop->getType();
            if ($type instanceof ReflectionNamedType) {
                $typeName = $type->getName();
                $nullable = $type->allowsNull();
            } else {
                $this->logger->warning(Message::GeneratorPropertyHasNoType->interpolate(name: $name, rc: $fqcn));
                $typeName = null;
                $nullable = true;
            }

            $schema = $this->propertySchema($typeName, $name, $fqcn, $prop);
            if ($nullable && $typeName !== null) {
                $schema = self::nullable($schema);
            }
            $this->applyPropertyAnnotation($prop, $schema);
            if ($prop->isReadOnly() || $prop->isProtectedSet() || $prop->isPrivateSet()) {
                $schema['readOnly'] = true;
            }
            $properties[$name] = $schema;
            if (!$nullable && !$prop->hasDefaultValue() && !self::promotedWithDefault($prop)) {
                $required[] = $name;
            }
        }

        $schema = ['type' => 'object', 'properties' => $properties];
        if ($required !== []) {
            $schema['required'] = $required;
        }
        return $schema;
    }

    /** A promoted constructor property's default lives on the parameter. */
    private static function promotedWithDefault(ReflectionProperty $prop): bool
    {
        if (!$prop->isPromoted()) {
            return false;
        }
        foreach ($prop->getDeclaringClass()->getConstructor()?->getParameters() ?? [] as $param) {
            if ($param->getName() === $prop->getName()) {
                return $param->isDefaultValueAvailable();
            }
        }
        return false;
    }

    /**
     * $schema that also allows null: `type: [x, "null"]` (with null among an enum's values), or anyOf for a $ref.
     * @param array<string, mixed> $schema
     * @return array<string, mixed>
     */
    public static function nullable(array $schema): array
    {
        if (isset($schema['$ref'])) {
            return ['anyOf' => [$schema, ['type' => 'null']]];
        }
        if (!isset($schema['type']) || !is_string($schema['type'])) {
            return $schema;
        }
        $schema['type'] = [$schema['type'], 'null'];
        if (isset($schema['enum']) && is_array($schema['enum'])) {
            $schema['enum'][] = null;
        }
        return $schema;
    }

    /** @return array<string, mixed> */
    private function propertySchema(?string $typeName, string $propertyName, string $ownerFqcn, ?ReflectionProperty $prop = null): array
    {
        if ($typeName === null || in_array($typeName, ['mixed', 'object', 'callable', 'self', 'static', 'null'], true)) {
            return [];
        }
        if (self::isScalar($typeName)) {
            return ['type' => self::mapType($typeName)];
        }
        if ($typeName === 'array' || $typeName === 'iterable') {
            return $this->arraySchema($prop !== null ? DocType::element($prop) : null);
        }
        if (!class_exists($typeName) && !interface_exists($typeName) && !enum_exists($typeName)) {
            $this->logger->warning(Message::GeneratorPropertyDoesNotExist->interpolate(property: $typeName, name: $propertyName, rc: $ownerFqcn));
            return [];
        }
        return $this->typeSchema($typeName);
    }

    /**
     * An array from its docblock's element type: `list<X>` / `X[]` as items, `array<string, X>` as additionalProperties.
     * @param array{0: 'list'|'map', 1: string}|null $element
     * @return array<string, mixed>
     */
    public function arraySchema(?array $element): array
    {
        if ($element === null) {
            return ['type' => 'array'];
        }
        [$kind, $type] = $element;
        $items = $this->typeSchema($type);
        return $kind === 'map' ? ['type' => 'object', 'additionalProperties' => $items] : ['type' => 'array', 'items' => $items];
    }

    /**
     * A scalar keyword, an enum (inline, with its values) or a class (by $ref).
     * @return array<string, mixed>
     */
    public function typeSchema(string $type): array
    {
        if (self::isScalar($type)) {
            return ['type' => self::mapType($type)];
        }
        if (enum_exists($type)) {
            return self::enumSchema($type);
        }
        return class_exists($type) || interface_exists($type) ? ['$ref' => $this->refFor($type)] : [];
    }

    /**
     * `{type, enum}`: a backed enum's values, a pure enum's case names.
     * @return array<string, mixed>
     */
    public static function enumSchema(string $enum): array
    {
        if (!enum_exists($enum)) {
            return [];
        }
        $reflection = new \ReflectionEnum($enum);
        $backing = (string) $reflection->getBackingType();
        $values = array_map(
            static fn (\ReflectionEnumUnitCase $case): int|string => $case instanceof \ReflectionEnumBackedCase ? $case->getBackingValue() : $case->getName(),
            $reflection->getCases()
        );
        return ['type' => $backing === 'int' ? 'integer' : 'string', 'enum' => $values];
    }

    /**
     * Merges #[Property(...)]'s description/format/example/deprecated.
     * @param array<string, mixed> $schema
     */
    private function applyPropertyAnnotation(ReflectionProperty $prop, array &$schema): void
    {
        $annotation = ($prop->getAttributes(Property::class)[0] ?? null)?->newInstance();
        if ($annotation === null) {
            return;
        }
        foreach (['description' => $annotation->description, 'format' => $annotation->format, 'example' => $annotation->example] as $key => $value) {
            if ($value !== null) {
                $schema[$key] = $value;
            }
        }
        if ($annotation->deprecated) {
            $schema['deprecated'] = true;
        }
    }

    /**
     * Schema for a path/query parameter: unknown/untyped is a string, a class or interface adds no constraint.
     * @return array<string, mixed>|stdClass
     */
    public static function parameterSchema(?string $type): array|stdClass
    {
        return match (true) {
            $type === null => ['type' => 'string'],
            self::isScalar($type) => ['type' => self::mapType($type)],
            $type === 'array', $type === 'iterable' => ['type' => 'array'],
            enum_exists($type) => self::enumSchema($type),
            default => new stdClass(),
        };
    }

    public static function isScalar(?string $type): bool
    {
        return $type !== null && (in_array($type, self::SCALARS, true) || $type === 'number');
    }

    public static function mapType(string $phpType): string
    {
        return match ($phpType) {
            'int', 'integer' => 'integer',
            'float', 'double', 'number' => 'number',
            'bool', 'boolean' => 'boolean',
            default => 'string',
        };
    }
}
