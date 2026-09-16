<?php

namespace Waypoint;

use RuntimeException;
use Stringable;

class Waypoint {

    /**
     * @var App|null
     */
    private static ?App $instance = null;

    /**
     * Creates (if not exists) and returns the App instance.
     */
    public static function create(): App {
        if (self::$instance === null) {
            self::$instance = new App();
        }

        return self::$instance;
    }

    /**
     * Returns the already created App instance (or null if not created).
     */
    public static function getInstance(): ?App {
        return self::$instance;
    }

    /**
     * Resets the instance (for testing or reloading).
     */
    public static function reset(): void {
        self::$instance = null;
    }

    public static function getRouter(): Router
    {
        // @codeCoverageIgnoreStart
        // Every real call path goes through create() first (App::attach(),
        // Waypoint::getConfig(), or a test's own setUp()); this only turns
        // a theoretical "called before create()" misuse into a clear
        // exception instead of a fatal null method call.
        if (self::$instance === null) {
            throw new RuntimeException('Waypoint::getRouter() called before Waypoint::create().');
        }
        // @codeCoverageIgnoreEnd
        return self::$instance->getRouter();
    }

    public static function getContainer(): Container
    {
        // @codeCoverageIgnoreStart
        // Same reasoning as getRouter() above.
        if (self::$instance === null) {
            throw new RuntimeException('Waypoint::getContainer() called before Waypoint::create().');
        }
        // @codeCoverageIgnoreEnd
        return self::$instance->getContainer();
    }

    /**
     * @template T of object
     * @param class-string<T> $className
     * @return T
     */
    public static function getConfig(string $className): object
    {
        return self::getContainer()->get($className);
    }
}