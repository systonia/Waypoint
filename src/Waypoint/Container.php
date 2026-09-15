<?php

namespace Waypoint;

use ReflectionClass;
use Throwable;

use Psr\Container\ContainerInterface;

use Waypoint\Exceptions\{ContainerException, NotFoundException};

/**
 * Undocumented class
 */
class Container implements ContainerInterface
{
    /** @var array<class-string, object> */
    private array $services = [];

    public function set(object $service): void
    {
        $this->services[get_class($service)] = $service;
    }

    /**
     * @template T of object
     * @param class-string<T> $id
     * @return T
     */
    public function get(string $id)
    {
        // The `instanceof $id` check is always true here in practice --
        // set() only ever keys an entry by that instance's own class -- but
        // it's also what lets PHPStan narrow $this->services[$id] (plain
        // `object`, since a heterogeneous array can't correlate its own
        // keys to per-entry value types) to T.
        if (isset($this->services[$id]) && $this->services[$id] instanceof $id) {
            return $this->services[$id];
        }
        if ($this->isAutoInstantiable($id)) {
            try {
                $instance = (new ReflectionClass($id))->newInstance();
            } catch (Throwable $e) {
                throw new ContainerException("Failed to instantiate service '$id': " . $e->getMessage(), 0, $e);
            }
            $this->set($instance);
            return $instance;
        }
        throw new NotFoundException("Service '$id' not found");
    }

    /** @param class-string $class */
    public function has(string $class): bool
    {
        return isset($this->services[$class]) || $this->isAutoInstantiable($class);
    }

    /**
     * True only if $class has already been constructed and registered (via
     * set(), or a prior get() auto-instantiation) -- unlike has(), this
     * doesn't return true just because $class *could* be auto-instantiated
     * on demand. Lets a caller tell "already configured" apart from
     * "constructible with defaults".
     */
    /** @param class-string $class */
    public function isRegistered(string $class): bool
    {
        return isset($this->services[$class]);
    }

    /**
     * True if $class exists and has no required constructor parameters.
     * @param class-string $class
     */
    private function isAutoInstantiable(string $class): bool
    {
        if (!class_exists($class)) {
            return false;
        }
        $constructor = (new ReflectionClass($class))->getConstructor();
        return !$constructor || $constructor->getNumberOfRequiredParameters() === 0;
    }

    /** @return array<class-string, object> */
    public function all(): array
    {
        return $this->services;
    }
}

