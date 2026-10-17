<?php

namespace Waypoint\PHPStan;

use PhpParser\Node;
use PHPStan\Analyser\Error;
use PHPStan\Analyser\IgnoreErrorExtension;
use PHPStan\Analyser\Scope;
use Waypoint\Attributes\ListOf;
use Waypoint\Attributes\Returns;

/**
 * #[Returns] and #[ListOf] name an array's element type, so PHPStan does not ask for a docblock with it
 * (missingType.iterableValue): on a method's return type with #[Returns], on a property or a constructor
 * parameter with #[ListOf]. Loaded by PHPStan only (extension.neon).
 */
final class AttributeTypes implements IgnoreErrorExtension
{
    public function shouldIgnore(Error $error, Node $node, Scope $scope): bool
    {
        if ($error->getIdentifier() !== 'missingType.iterableValue') {
            return false;
        }
        $class = $scope->getClassReflection()?->getNativeReflection();
        $function = $scope->getFunction();
        if ($class === null) {
            return false;
        }
        if ($function === null) {
            // A property's error is reported in the class scope: "Property A::$tags type has no value type ..."
            return preg_match('/::\$(\w+) type /', $error->getMessage(), $match) === 1
                && $class->hasProperty($match[1]) && $class->getProperty($match[1])->getAttributes(ListOf::class) !== [];
        }
        $method = $class->getMethod($function->getName());
        if (preg_match('/ has parameter \$(\w+) /', $error->getMessage(), $match) === 1) {
            foreach ($method->getParameters() as $parameter) {
                if ($parameter->getName() === $match[1]) {
                    return $parameter->getAttributes(ListOf::class) !== [];
                }
            }
            return false;
        }
        return str_contains($error->getMessage(), ' return type ') && $method->getAttributes(Returns::class) !== [];
    }
}
