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

/**
 * Builds and collects the `components.schemas` of a spec: one object schema
 * per DTO class (public properties, typed; nested DTOs by $ref, recursion-
 * safe), plus the shared RFC 9457 ProblemDetails schema. Also owns the
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
     * An object schema from $fqcn's public properties. A non-nullable property is required:
     * PHP defaults aren't a usable "optional" signal, since FromArray DTOs give every
     * property some type-safe default.
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

            $schema = $this->propertySchema($typeName, $name, $fqcn);
            $this->applyPropertyAnnotation($prop, $schema);
            $properties[$name] = $schema;
            if (!$nullable) {
                $required[] = $name;
            }
        }

        $schema = ['type' => 'object', 'properties' => $properties];
        if ($required !== []) {
            $schema['required'] = $required;
        }
        return $schema;
    }

    /** @return array<string, mixed> */
    private function propertySchema(?string $typeName, string $propertyName, string $ownerFqcn): array
    {
        if ($typeName === null || in_array($typeName, ['mixed', 'object', 'callable', 'self', 'static', 'null'], true)) {
            return [];
        }
        if (self::isScalar($typeName)) {
            return ['type' => self::mapType($typeName)];
        }
        if ($typeName === 'array' || $typeName === 'iterable') {
            return ['type' => 'array'];
        }
        if (!class_exists($typeName) && !interface_exists($typeName) && !enum_exists($typeName)) {
            $this->logger->warning(Message::GeneratorPropertyDoesNotExist->interpolate(property: $typeName, name: $propertyName, rc: $ownerFqcn));
            return [];
        }
        return ['$ref' => $this->refFor($typeName)];
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
