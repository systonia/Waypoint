<?php

namespace Waypoint\PHPStan;

use PhpParser\Node\Expr;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\PhpDoc\TypeStringResolver;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Type\ExpressionTypeResolverExtension;
use PHPStan\Type\MixedType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use Waypoint\Attributes\ListOf;
use Waypoint\Attributes\Returns;

/**
 * Reading a #[ListOf] property gives the attribute's type (`list<OrderLine>`), so does calling a #[Returns] method
 * (`list<Product>`): the attribute is the only place that names the elements. property() and method() give that
 * type to AttributeTypeRule too. Loaded by PHPStan only (extension.neon).
 */
final class AttributeTypeResolver implements ExpressionTypeResolverExtension
{
    public function __construct(private TypeStringResolver $types)
    {
    }

    public function getType(Expr $expr, Scope $scope): ?Type
    {
        if ($expr instanceof Expr\ArrayDimFetch && $expr->dim !== null) {
            // An offset of the attribute's array: PHPStan would take it from the native `array` when narrowing
            return $this->getType($expr->var, $scope)?->getOffsetValueType($scope->getType($expr->dim));
        }
        $property = $expr instanceof Expr\PropertyFetch;
        if ((!$property && !$expr instanceof Expr\MethodCall) || !$expr->name instanceof Identifier) {
            return null;
        }
        $receiver = $scope->getType($expr->var);
        $classes = $receiver->getObjectClassReflections();
        if ($classes === [] && TypeCombinator::removeNull($receiver)->isObject()->maybe()) {
            // A receiver PHPStan knows nothing of: mixed, as without this extension. Declining instead would also
            // decline the PHPDoc flavour of the same expression, which PHPStan asks after the native one: a
            // `$order` typed only by PHPDoc is mixed natively, and `foreach ($order->lines as $line)` would lose its type
            return new MixedType();
        }
        if (count($classes) !== 1) {
            return null;
        }
        return $property ? $this->property($classes[0], $expr->name->name) : $this->method($classes[0], $expr->name->name);
    }

    /**
     * The type #[ListOf] gives a property and #[Returns] a method: `list<T>`, `array<string, T>` or T itself
     * (#[Returns] without list and map), with null added when the native type allows it. Null when there is no
     * attribute, or when the attribute's type does not narrow the native one (a method typed Response that
     * documents its body).
     */
    public function property(ClassReflection $class, string $name): ?Type
    {
        if (!$class->hasNativeProperty($name)) {
            return null;
        }
        $attribute = $class->getNativeReflection()->getProperty($name)->getAttributes(ListOf::class)[0] ?? null;
        if ($attribute === null) {
            return null;
        }
        $args = $attribute->getArguments();
        $map = ($args['map'] ?? $args[1] ?? false) === true;
        return $this->narrow($args['type'] ?? $args[0] ?? null, !$map, $map, $class->getNativeProperty($name)->getNativeType());
    }

    public function method(ClassReflection $class, string $name): ?Type
    {
        if (!$class->hasNativeMethod($name)) {
            return null;
        }
        $attribute = $class->getNativeReflection()->getMethod($name)->getAttributes(Returns::class)[0] ?? null;
        if ($attribute === null) {
            return null;
        }
        $args = $attribute->getArguments();
        $list = ($args['list'] ?? $args[1] ?? false) === true;
        $map = ($args['map'] ?? $args[2] ?? false) === true;
        return $this->narrow($args['type'] ?? $args[0] ?? null, $list, $map, $class->getNativeMethod($name)->getOnlyVariant()->getNativeReturnType());
    }

    private function narrow(mixed $element, bool $list, bool $map, Type $native): ?Type
    {
        if (!is_string($element) || preg_match('/^\\\\?[A-Za-z_][\w\\\\]*$/', $element) !== 1) {
            return null;
        }
        $element = in_array(strtolower($element), ['int', 'string', 'float', 'bool', 'mixed'], true) ? $element : '\\' . ltrim($element, '\\');
        $type = $this->types->resolve(match (true) {
            $map => "array<string, $element>",
            $list => "list<$element>",
            default => $element,
        });
        if (TypeCombinator::containsNull($native)) {
            $type = TypeCombinator::addNull($type);
        }
        return $native->isSuperTypeOf($type)->yes() ? $type : null;
    }
}
