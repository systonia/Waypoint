<?php

namespace Waypoint;

/**
 * The array constructor #[Body] DTOs need (Router constructs them as
 * `new $class($req->body())`): each key that names a property is assigned,
 * unknown keys are ignored, and a value that doesn't fit the property's type
 * is skipped so the Validator can report it as a 422 instead of a TypeError 500.
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
            if (!property_exists($this, $key)) {
                continue;
            }
            try {
                $this->$key = $value;
            } catch (\TypeError) {
                continue;
            }
        }
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
