<?php

namespace Waypoint\Support;

use Fiber;
use WeakMap;

/**
 * One value per Fiber, for state that belongs to a single request. A runtime that runs several requests
 * interleaved in Fibers of one process (each request in its own Fiber) then keeps them apart. Outside a
 * Fiber (FPM, php -S, CLI) it is a single slot, which a Fiber without its own value also sees.
 *
 * @template T
 */
final class FiberLocal
{
    /** @var WeakMap<object, T> Fiber => value, gone with the Fiber. */
    private WeakMap $byFiber;

    /** @var T|null */
    private mixed $main = null;

    public function __construct()
    {
        $this->byFiber = new WeakMap();
    }

    /** @return T|null */
    public function get(): mixed
    {
        $fiber = Fiber::getCurrent();
        return $fiber !== null && isset($this->byFiber[$fiber]) ? $this->byFiber[$fiber] : $this->main;
    }

    /** @param T|null $value null forgets this Fiber's value */
    public function set(mixed $value): void
    {
        $fiber = Fiber::getCurrent();
        if ($fiber === null) {
            $this->main = $value;
        } elseif ($value === null) {
            unset($this->byFiber[$fiber]);
        } else {
            $this->byFiber[$fiber] = $value;
        }
    }
}
