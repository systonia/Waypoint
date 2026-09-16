<?php

namespace Waypoint;

use ReflectionObject;
use ReflectionProperty;
use RuntimeException;
use Stringable;
use Waypoint\Attributes\{Sensitive, PII};

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

    /**
     * Builds a safe-to-log/display copy of $target's public properties:
     * each one's real value, except any marked #[Sensitive] (a
     * credential/secret) or #[PII] (personally identifiable information,
     * GDPR/DSGVO) -- those come back as that attribute's own
     * $placeholder ('**redacted**' unless overridden) instead of the
     * real value. The one place this redaction logic lives, so nothing
     * that wants to log/display a DTO safely (a request logger, an error
     * report, ...) has to hand-roll its own field-name blocklist.
     *
     * Generic on purpose -- works on any object, not just a FromArray
     * user: reflects $target's own public, non-static, *initialized*
     * properties (a typed property never assigned a value can't be read
     * at all, so it's skipped rather than fatal-erroring here).
     *
     * @return array<string, mixed>
     */
    public static function redact(object $target): array
    {
        $result = [];

        foreach ((new ReflectionObject($target))->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->isStatic() || !$property->isInitialized($target)) {
                continue;
            }

            $placeholder = self::redactionPlaceholder($property);
            $result[$property->getName()] = $placeholder ?? $property->getValue($target);
        }

        return $result;
    }

    /** The #[Sensitive]/#[PII] placeholder for $property, or null if it carries neither. */
    private static function redactionPlaceholder(ReflectionProperty $property): ?string
    {
        foreach ($property->getAttributes(Sensitive::class) as $attribute) {
            return $attribute->newInstance()->placeholder;
        }
        foreach ($property->getAttributes(PII::class) as $attribute) {
            return $attribute->newInstance()->placeholder;
        }

        return null;
    }
}