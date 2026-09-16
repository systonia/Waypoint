<?php

namespace Waypoint;

/**
 * The array constructor #[Body] DTOs need (Router constructs them as
 * `new $class($req->body())`): each key that names a property is assigned,
 * unknown keys are ignored, and a value that doesn't fit the property's type
 * is skipped so the Validator can report it as a 422 instead of a TypeError 500.
 */
trait FromArray
{
    /** @param array<string, mixed> $data */
    public function __construct(array $data = [])
    {
        $this->hydrateFromArray($data);
    }

    /**
     * Split out so a using class can declare its own __construct() and still call this.
     * @param array<string, mixed> $data
     */
    protected function hydrateFromArray(array $data): void
    {
        $excludes = $this->fromArrayExcludes();
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
