<?php

namespace Waypoint;

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
        return self::$instance->getRouter();
    }

    public static function getContainer(): Container
    {
        return self::$instance->getContainer();
    }

    public static function getConfig(string $className): mixed
    {
        return self::getContainer()->get($className);
    }
}