<?php

namespace Waypoint;

use ReflectionObject;
use ReflectionProperty;
use RuntimeException;
use Stringable;
use Waypoint\Attributes\{Sensitive, PII};

/** Process-wide access to the single App: create()/getInstance()/reset(), plus getConfig() for any Options class. */
class Waypoint
{

    private static ?App $instance = null;

    public static function create(): App {
        if (self::$instance === null) {
            self::$instance = new App();
        }

        return self::$instance;
    }

    public static function getInstance(): ?App {
        return self::$instance;
    }

    public static function reset(): void {
        self::$instance = null;
    }

    public static function getRouter(): Router
    {
        // @codeCoverageIgnoreStart
        if (self::$instance === null) {
            throw new RuntimeException('Waypoint::getRouter() called before Waypoint::create().');
        }
        // @codeCoverageIgnoreEnd
        return self::$instance->getRouter();
    }

    public static function getContainer(): Container
    {
        // @codeCoverageIgnoreStart
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
     * A safe-to-log copy of $target's public, initialized properties: any marked #[Sensitive] or
     * #[PII] comes back as that attribute's placeholder instead of its value.
     * @return array<string, mixed>
     */
    public static function redact(object $target): array
    {
        $result = [];

        foreach ((new ReflectionObject($target))->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->isStatic() || $property->isVirtual() || !$property->isInitialized($target)) {
                continue;
            }

            $placeholder = self::redactionPlaceholder($property);
            $result[$property->getName()] = $placeholder ?? $property->getValue($target);
        }

        return $result;
    }

    private static function redactionPlaceholder(ReflectionProperty $property): ?string
    {
        foreach ([Sensitive::class, PII::class] as $attribute) {
            $attrs = $property->getAttributes($attribute);
            if ($attrs !== []) {
                return $attrs[0]->newInstance()->placeholder;
            }
        }
        return null;
    }
}