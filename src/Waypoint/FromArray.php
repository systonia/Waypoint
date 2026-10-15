<?php

namespace Waypoint;

/**
 * The array constructor #[Body] DTOs need (Router constructs them as
 * `new $class($req->body())`): each key that names a publicly writable
 * instance property is assigned, unknown keys are ignored, and a value that
 * doesn't fit the property's type is left out and recorded as a failure (a 422,
 * not a TypeError 500, and never cast: "false" is no true). A form's text for a
 * number or a boolean ("5", "on") is converted. Private, protected, protected(set)/
 * private(set) and static properties are never touched: the data comes from
 * the request, and those are the DTO's own state.
 *
 * Validation is read straight off the DTO: `$dto->isValid` and `$dto->errors`
 * apply the rule attributes on every read, and `fail()` adds a failure a
 * controller found itself (a taken email, say) to the same list.
 */
trait FromArray
{
    /** @var array<string, string> Failures added through fail(). */
    private array $failures = [];

    /** @var array<string, string> field => message; attribute rules first, then fail() entries. */
    public array $errors {
        get => (new Validator())->validate($this) + $this->failures;
    }

    public bool $isValid {
        get => $this->errors === [];
    }

    /** @param array<string, mixed> $data */
    public function __construct(array $data = [])
    {
        $this->hydrateFromArray($data);
    }

    /** Records a failure the attributes can't know about; a later one for the same field replaces the earlier. */
    public function fail(string $field, string $message): static
    {
        $this->failures[$field] = $message;
        return $this;
    }

    /**
     * Split out so a using class can declare its own __construct() and still call this.
     * @param array<string, mixed> $data
     */
    protected function hydrateFromArray(array $data): void
    {
        $excludes = [...$this->fromArrayExcludes(), 'errors', 'isValid', 'failures'];
        foreach ($data as $key => $value) {
            if (in_array($key, $excludes, true)) {
                continue;
            }
            if (!self::isWritableFromInput($this, $key)) {
                continue;
            }
            $property = new \ReflectionProperty($this, $key);
            [$fits, $value] = self::fitting($property->getType(), $value);
            if (!$fits) {
                // Left at its default, and a 422: never cast ("false" is no true, 2.7 no 2)
                $this->failures[$key] = 'Expected a value of type ' . $property->getType() . '.';
                continue;
            }
            try {
                $this->$key = $value;
            } catch (\TypeError) {
                $this->failures[$key] = 'Expected a value of type ' . $property->getType() . '.';
            }
        }
    }

    /**
     * Whether $value fits $type as it is, or as the text a form sends for a number or a boolean ("5", "2.5", "on",
     * "false"), and the value to assign then. Nothing else is converted.
     * @return array{bool, mixed}
     */
    private static function fitting(?\ReflectionType $type, mixed $value): array
    {
        if ($type === null) {
            return [true, $value];
        }
        if ($value === null) {
            return [$type->allowsNull(), null];
        }
        $names = $type instanceof \ReflectionNamedType ? [$type->getName()]
            : ($type instanceof \ReflectionUnionType ? array_map(static fn (\ReflectionType $t): string => $t instanceof \ReflectionNamedType ? $t->getName() : '', $type->getTypes()) : []);
        // An intersection type: PHP's own check decides
        if ($names === []) {
            return [true, $value];
        }
        foreach ($names as $name) {
            $exact = match ($name) {
                'mixed' => true,
                'int' => is_int($value),
                'float' => is_float($value) || is_int($value),
                'bool', 'false', 'true' => is_bool($value) && ($name === 'bool' || $value === ($name === 'true')),
                'string' => is_string($value),
                'array', 'iterable' => is_array($value),
                default => is_object($value) && $value instanceof $name,
            };
            if ($exact) {
                return [true, $name === 'float' && is_int($value) ? (float) $value : $value];
            }
        }
        foreach ($names as $name) {
            $converted = is_string($value) ? match ($name) {
                'int' => filter_var($value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE),
                'float' => filter_var($value, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE),
                'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
                default => null,
            } : null;
            if ($converted !== null) {
                return [true, $converted];
            }
        }
        return [false, null];
    }

    /** True for a public, non-static property whose set visibility is public too (no protected(set)/private(set)). */
    private static function isWritableFromInput(object $target, string $name): bool
    {
        if (!property_exists($target, $name)) {
            return false;
        }
        $property = new \ReflectionProperty($target, $name);
        return $property->isPublic() && !$property->isStatic() && !$property->isProtectedSet() && !$property->isPrivateSet();
    }

    /**
     * Property names hydration skips (e.g. a nested DTO set some other way). A method, since a trait property's default can't be overridden.
     * @return array<int, string>
     */
    protected function fromArrayExcludes(): array
    {
        return [];
    }
}
