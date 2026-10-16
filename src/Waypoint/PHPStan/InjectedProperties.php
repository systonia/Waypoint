<?php

namespace Waypoint\PHPStan;

use PHPStan\Reflection\PropertyReflection;
use PHPStan\Rules\Properties\ReadWritePropertiesExtension;
use Waypoint\Attributes\Inject;

/**
 * The container writes #[Inject] properties: to PHPStan they would only ever be read, and never initialized. They
 * count as written and initialized. Loaded by PHPStan only (extension.neon, included through phpstan/extension-installer).
 */
final class InjectedProperties implements ReadWritePropertiesExtension
{
    public function isAlwaysRead(PropertyReflection $property, string $propertyName): bool
    {
        return false;
    }

    public function isAlwaysWritten(PropertyReflection $property, string $propertyName): bool
    {
        return self::isInjected($property, $propertyName);
    }

    public function isInitialized(PropertyReflection $property, string $propertyName): bool
    {
        return self::isInjected($property, $propertyName);
    }

    private static function isInjected(PropertyReflection $property, string $propertyName): bool
    {
        $class = $property->getDeclaringClass()->getNativeReflection();
        return $class->hasProperty($propertyName) && $class->getProperty($propertyName)->getAttributes(Inject::class) !== [];
    }
}
