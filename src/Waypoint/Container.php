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
    private array $services = [];

    public function set(object $service): void
    {
        $this->services[get_class($service)] = $service;
    }

    public function get(string $id)
    {
        if (isset($this->services[$id])) {
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
    public function isRegistered(string $class): bool
    {
        return isset($this->services[$class]);
    }

    /** True if $class exists and has no required constructor parameters. */
    private function isAutoInstantiable(string $class): bool
    {
        if (!class_exists($class)) {
            return false;
        }
        $constructor = (new ReflectionClass($class))->getConstructor();
        return !$constructor || $constructor->getNumberOfRequiredParameters() === 0;
    }

    public function all(): array
    {
        return $this->services;
    }
}

