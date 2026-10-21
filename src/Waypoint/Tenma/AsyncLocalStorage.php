<?php

namespace Waypoint\Tenma;

use Threading\AsyncLocal;
use Waypoint\Support\LocalStorage;

/** A FiberLocal's value in Tenma's async context: a task a request starts gets a copy of it. */
final class AsyncLocalStorage implements LocalStorage
{
    /** @var AsyncLocal<mixed> */
    private AsyncLocal $local;

    public function __construct()
    {
        $this->local = new AsyncLocal();
    }

    public function get(): mixed
    {
        return $this->local->get();
    }

    public function set(mixed $value): void
    {
        $this->local->set($value);
    }
}
