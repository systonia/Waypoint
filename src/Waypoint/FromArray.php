<?php

namespace Waypoint;

/**
 * The array constructor #[Body] DTOs need (Router constructs them as
 * `new $class($req->body())`): each key that names a publicly writable
 * instance property is assigned, unknown keys are ignored, and a value that
 * doesn't fit the property's type is left out and recorded as a failure (a 422,
 * not a TypeError 500, and never cast: "false" is no true). A form's text for a
 * number or a boolean ("5", "on") is converted, a backed enum property gets the
 * case its value names, and a property typed with another FromArray DTO (or an
 * array whose `@var list<Dto>` says so) is built from the nested array. Private, protected, protected(set)/
 * private(set) and static properties are never touched: the data comes from
 * the request, and those are the DTO's own state.
 *
 * Validation is read straight off the DTO: `$dto->isValid` and `$dto->errors`
 * apply the rule attributes on every read, and `fail()` adds a failure a
 * controller found itself (a taken email, say) to the same list. A writable
 * property without a default that the data left unset is "This value is required.".
 */
trait FromArray
{
    /** @var array<string, string> Failures added through fail(). */
    private array $failures = [];

    /** @var array<string, string> field => message; attribute rules first, then fail() entries, required fields left unset, and nested DTOs' errors ("address.city"). */
    public array $errors {
        get => (new Validator())->validate($this) + $this->failures + $this->missingRequired() + $this->nestedErrors();
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
            [$fits, $value, $message] = self::fitting($property->getType(), $value);
            if (!$fits) {
                // Left at its default, and a 422: never cast ("false" is no true, 2.7 no 2)
                $this->failures[$key] = $message ?? 'Expected a value of type ' . $property->getType() . '.';
                continue;
            }
            if (is_array($value)) {
                $value = $this->elements($property, $value);
            }
            try {
                $this->$key = $value;
            } catch (\TypeError) {
                $this->failures[$key] = 'Expected a value of type ' . $property->getType() . '.';
            }
        }
    }

    /**
     * The elements of an array as the property's `@var list<Item>` (or `Item[]`, `array<string, Item>`) says: a
     * FromArray DTO built from each array, a backed enum case from each value. One that doesn't fit is left out and
     * reported under "property.index".
     * @param array<array-key, mixed> $items
     * @return array<array-key, mixed>
     */
    private function elements(\ReflectionProperty $property, array $items): array
    {
        $class = Support\DocType::element($property)[1] ?? null;
        if ($class === null || (!self::hydratable($class) && !is_subclass_of($class, \BackedEnum::class))) {
            return $items;
        }
        $result = [];
        foreach ($items as $index => $item) {
            [$fits, $converted, $message] = $item instanceof $class ? [true, $item, null] : self::converted($class, $item);
            if ($fits) {
                $result[$index] = $converted;
            } else {
                $this->failures[$property->getName() . ".$index"] = $message ?? "Expected a value of type {$class}.";
            }
        }
        return array_is_list($items) ? array_values($result) : $result;
    }

    /**
     * Whether $value fits $type as it is, or as the text a form sends for a number or a boolean ("5", "2.5", "on",
     * "false"), a backed enum's value, or the array of a nested FromArray DTO; the value to assign then, and why
     * it doesn't fit when there is more to say than its type.
     * @return array{bool, mixed, ?string}
     */
    private static function fitting(?\ReflectionType $type, mixed $value): array
    {
        if ($type === null) {
            return [true, $value, null];
        }
        if ($value === null) {
            return [$type->allowsNull(), null, null];
        }
        $names = $type instanceof \ReflectionNamedType ? [$type->getName()]
            : ($type instanceof \ReflectionUnionType ? array_map(static fn (\ReflectionType $t): string => $t instanceof \ReflectionNamedType ? $t->getName() : '', $type->getTypes()) : []);
        // An intersection type: PHP's own check decides
        if ($names === []) {
            return [true, $value, null];
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
                return [true, $name === 'float' && is_int($value) ? (float) $value : $value, null];
            }
        }
        $failure = [false, null, null];
        foreach ($names as $name) {
            $converted = self::converted($name, $value);
            if ($converted[0]) {
                return $converted;
            }
            $failure = $converted[2] !== null ? $converted : $failure;
        }
        return $failure;
    }

    /**
     * $value as $name by conversion: form text as int/float/bool, a backed enum case by its value, a FromArray DTO
     * from an array.
     * @return array{bool, mixed, ?string}
     */
    private static function converted(string $name, mixed $value): array
    {
        if (is_subclass_of($name, \BackedEnum::class)) {
            $backing = (string) (new \ReflectionEnum($name))->getBackingType();
            $raw = $backing === 'int' && is_string($value) ? filter_var($value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE) : $value;
            $case = ($backing === 'int' && is_int($raw)) || ($backing === 'string' && is_string($raw)) ? $name::tryFrom($raw) : null;
            if ($case !== null) {
                return [true, $case, null];
            }
            $values = implode(', ', array_map(static fn (\BackedEnum $c): string => (string) $c->value, $name::cases()));
            return [false, null, "This value should be one of: {$values}."];
        }
        if (is_array($value) && self::hydratable($name)) {
            return [true, new $name($value), null];
        }
        $converted = is_string($value) ? match ($name) {
            'int' => filter_var($value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE),
            'float' => filter_var($value, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE),
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            default => null,
        } : null;
        return $converted !== null ? [true, $converted, null] : [false, null, null];
    }

    /** Whether $class uses FromArray (itself or through a parent): built from an array, validated through its own $errors. */
    private static function hydratable(string $class): bool
    {
        if (!class_exists($class)) {
            return false;
        }
        foreach ([$class, ...array_values(class_parents($class) ?: [])] as $c) {
            if (in_array(FromArray::class, class_uses($c) ?: [], true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * The errors of nested FromArray DTOs, a property's or a list's, under "property.field" or "property.index.field".
     * @return array<string, string>
     */
    private function nestedErrors(): array
    {
        $errors = [];
        foreach ((new \ReflectionObject($this))->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->isStatic() || $property->isVirtual() || !$property->isInitialized($this)) {
                continue;
            }
            $value = $property->getValue($this);
            foreach (is_array($value) ? $value : ['' => $value] as $index => $item) {
                if (!is_object($item) || !self::hydratable($item::class) || !isset($item->errors) || !is_array($item->errors)) {
                    continue;
                }
                $prefix = $property->getName() . ($index === '' ? '' : ".$index");
                foreach ($item->errors as $field => $message) {
                    $errors["$prefix.$field"] = is_string($message) ? $message : '';
                }
            }
        }
        return $errors;
    }

    /**
     * A publicly writable property without a default that nothing set: required, and reading it would throw.
     * @return array<string, string>
     */
    private function missingRequired(): array
    {
        $missing = [];
        $excludes = $this->fromArrayExcludes();
        foreach ((new \ReflectionObject($this))->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            $name = $property->getName();
            if (!$property->isVirtual() && self::isWritableFromInput($this, $name) && !in_array($name, $excludes, true) && !$property->isInitialized($this)) {
                $missing[$name] = 'This value is required.';
            }
        }
        return $missing;
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
