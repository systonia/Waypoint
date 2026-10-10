<?php

namespace Waypoint\Support;

use Closure;
use Fiber;
use WeakMap;

/**
 * One value per Fiber, for state that belongs to a single request. A runtime that runs several requests
 * interleaved in Fibers of one process (each request in its own Fiber) then keeps them apart. Outside a
 * Fiber (FPM, php -S, CLI) it is a single slot, which a Fiber without its own value also sees.
 *
 * PHP doesn't know which Fiber started another, so a task a request starts in a Fiber of its own sees
 * nothing. A runtime that knows (see useStorage()) can hand the value on.
 *
 * @template T
 */
final class FiberLocal implements LocalStorage
{
    /** @var (Closure(): LocalStorage)|null */
    private static ?Closure $storage = null;

    /** Set on first use: the runtime's storage, or null for the per-Fiber map below. */
    private ?LocalStorage $delegate = null;

    private bool $resolved = false;

    /** @var WeakMap<object, T> Fiber => value, gone with the Fiber. */
    private WeakMap $byFiber;

    /** @var T|null */
    private mixed $main = null;

    public function __construct()
    {
        $this->byFiber = new WeakMap();
    }

    /**
     * Keeps the values of every FiberLocal used from now on in what $create returns, e.g. a runtime's async
     * context that follows a request into the tasks it starts; null goes back to one value per Fiber.
     *
     *   FiberLocal::useStorage(fn () => new class implements LocalStorage { ... Nope\AsyncLocal ... });
     *
     * @param (Closure(): LocalStorage)|null $create
     */
    public static function useStorage(?Closure $create): void
    {
        self::$storage = $create;
    }

    private function delegate(): ?LocalStorage
    {
        if (!$this->resolved) {
            $this->resolved = true;
            $this->delegate = self::$storage !== null ? (self::$storage)() : null;
        }
        return $this->delegate;
    }

    /** @return T|null */
    public function get(): mixed
    {
        $delegate = $this->delegate();
        if ($delegate !== null) {
            /** @var T|null */
            return $delegate->get();
        }
        $fiber = Fiber::getCurrent();
        return $fiber !== null && isset($this->byFiber[$fiber]) ? $this->byFiber[$fiber] : $this->main;
    }

    /** @param T|null $value null forgets this Fiber's value */
    public function set(mixed $value): void
    {
        $delegate = $this->delegate();
        if ($delegate !== null) {
            $delegate->set($value);
            return;
        }
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
