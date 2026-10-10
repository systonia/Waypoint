<?php

namespace Waypoint\Support;

/**
 * Where a FiberLocal keeps its value, see FiberLocal::useStorage(). A runtime with async context (such as
 * Tenma\AsyncLocal) lets the value follow a request into the tasks it starts.
 */
interface LocalStorage
{
    public function get(): mixed;

    /** null forgets the value */
    public function set(mixed $value): void;
}
