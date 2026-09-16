<?php

namespace Waypoint;

use ReflectionClass;
use Throwable;
use Psr\Container\ContainerInterface;
use Waypoint\Exceptions\{ContainerException, NotFoundException};

/** A class-name-keyed singleton registry; a class with no required constructor arguments is constructed on first get(). */
class Container implements ContainerInterface
{
    /** @var array<class-string, object> */
    private array $services = [];

    public function set(object $service): void
    {
        $this->services[get_class($service)] = $service;
    }

    /**
     * Registers $service under an interface (or parent class) name, so get(Interface::class) resolves it.
     * @param class-string $id
     */
    public function bind(string $id, object $service): void
    {
        if (!$service instanceof $id) {
            throw new ContainerException("Cannot bind '$id': " . get_class($service) . ' does not implement it.');
        }
        $this->services[$id] = $service;
    }

    /**
     * @template T of object
     * @param class-string<T> $id
     * @return T
     */
    public function get(string $id)
    {
        // The instanceof is what narrows the heterogeneous map entry to T for PHPStan.
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
     * True only if $class was already constructed (unlike has(), which is also true for "could be").
     * @param class-string $class
     */
    public function isRegistered(string $class): bool
    {
        return isset($this->services[$class]);
    }

    /** @param class-string $class */
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
