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

    /** @var WeakMap<FiberLocal<mixed>, true>|null Every FiberLocal, for capture(). */
    private static ?WeakMap $all = null;

    /** The runtime's storage, created on first use after useStorage(); until then the per-Fiber map below. */
    private ?LocalStorage $delegate = null;

    /** @var WeakMap<object, T> Fiber => value, gone with the Fiber. */
    private WeakMap $byFiber;

    /** @var T|null */
    private mixed $main = null;

    public function __construct()
    {
        $this->byFiber = new WeakMap();
        self::$all ??= new WeakMap();
        self::$all[$this] = true;
    }

    /**
     * Takes the current values of every FiberLocal and returns a function that sets them again, in whatever
     * Fiber or task calls it. For work that runs after the request was reset but belongs to it: App::handle()
     * captures before it resets an upgraded request, and Tenma\Bridge restores in the WebSocket handler.
     *
     * @return Closure(): void
     */
    public static function capture(): Closure
    {
        $values = [];
        foreach (self::$all ?? [] as $local => $_) {
            $value = $local->get();
            if ($value !== null) {
                $values[] = [$local, $value];
            }
        }
        return static function () use ($values): void {
            foreach ($values as [$local, $value]) {
                $local->set($value);
            }
        };
    }

    /**
     * Keeps the values of every FiberLocal in what $create returns from now on, e.g. a runtime's async context
     * that follows a request into the tasks it starts. Call it before the first request.
     *
     *   FiberLocal::useStorage(fn () => new class implements LocalStorage { ... Tenma\AsyncLocal ... });
     *
     * @param (Closure(): LocalStorage)|null $create
     */
    public static function useStorage(?Closure $create): void
    {
        self::$storage = $create;
    }

    /**
     * Switches over as soon as a storage is configured, even for a FiberLocal already used while the app
     * booted (the Logger reads the request id then); values it held in its own map stay behind.
     */
    private function delegate(): ?LocalStorage
    {
        if ($this->delegate === null && self::$storage !== null) {
            $this->delegate = (self::$storage)();
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
