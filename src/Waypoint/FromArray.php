<?php

namespace Waypoint;

/**
 * Hydrates every public property of the using class from a plain array
 * whose keys match property names -- the exact contract Router::
 * buildMethodArguments() relies on for every #[Body]/#[Body(of: ...)]
 * argument: it constructs the DTO directly as `new $class($req->body())`
 * (see Router's 'Body'/'BodyCollection' cases), so any class used that
 * way needs a constructor shaped like this one. `use FromArray;` on a DTO
 * is that constructor, so it doesn't have to be hand-written (and kept in
 * sync across every DTO in every app) separately each time.
 *
 * Unknown keys in $data are silently ignored (the property_exists()
 * guard) -- request bodies routinely carry extra fields a DTO doesn't
 * declare, and rejecting them isn't this constructor's job (#[Property]/
 * Validator handle actual shape enforcement).
 */
trait FromArray
{
    /** @param array<string, mixed> $data */
    public function __construct(array $data = [])
    {
        $this->hydrateFromArray($data);
    }

    /**
     * The actual hydration loop, split out from __construct() so a using
     * class that needs extra logic around it (e.g. RendererOptions
     * normalizing $directory afterward) can declare its own __construct()
     * -- which overrides the trait's, same as any other method a class
     * redeclares -- and still call this directly.
     *
     * @param array<string, mixed> $data
     */
    protected function hydrateFromArray(array $data): void
    {
        $excludes = $this->fromArrayExcludes();
        foreach ($data as $key => $value) {
            if (in_array($key, $excludes, true)) {
                continue;
            }
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    /**
     * Property names to skip during hydration -- e.g. a nested DTO
     * property that must be set some other way (#[Body] hydration is
     * flat: assigning a raw array straight to a typed object property
     * would TypeError instead of recursively hydrating it, so a
     * server-computed/cross-referenced property like that belongs here).
     * A method, not a property: PHP refuses to let a using class
     * redeclare a typed trait property with a different default value
     * ("the definition differs and is considered incompatible"), while
     * overriding a method is completely ordinary. Empty by default;
     * override in the using class to add entries.
     *
     * @return array<int, string>
     */
    protected function fromArrayExcludes(): array
    {
        return [];
    }
}
