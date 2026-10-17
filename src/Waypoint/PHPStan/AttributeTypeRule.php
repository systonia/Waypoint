<?php

namespace Waypoint\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Return_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\Type;
use PHPStan\Type\VerbosityLevel;

/**
 * What a #[Returns] method returns and what goes into a #[ListOf] property (an assignment, or a constructor argument
 * of a promoted parameter) must fit the attribute's type. Loaded by PHPStan only (extension.neon).
 *
 * @implements Rule<Node>
 */
final class AttributeTypeRule implements Rule
{
    public function __construct(private AttributeTypeResolver $elements, private ReflectionProvider $reflection)
    {
    }

    public function getNodeType(): string
    {
        return Node::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        return match (true) {
            $node instanceof Return_ => $this->returned($node, $scope),
            $node instanceof Expr\Assign => $this->assigned($node, $scope),
            $node instanceof Expr\New_ => $this->constructed($node, $scope),
            default => [],
        };
    }

    /** @return list<IdentifierRuleError> */
    private function returned(Return_ $node, Scope $scope): array
    {
        $class = $scope->getClassReflection();
        $function = $scope->getFunction();
        if ($node->expr === null || $class === null || $function === null || $scope->isInAnonymousFunction()) {
            return [];
        }
        $expected = $this->elements->method($class, $function->getName());
        return self::check($expected, $scope->getType($node->expr), $scope, 'waypoint.returns', "Method {$class->getName()}::{$function->getName()}() should return %s as #[Returns] says, %s returned.");
    }

    /** @return list<IdentifierRuleError> */
    private function assigned(Expr\Assign $node, Scope $scope): array
    {
        if (!$node->var instanceof Expr\PropertyFetch || !$node->var->name instanceof Identifier) {
            return [];
        }
        $classes = $scope->getType($node->var->var)->getObjectClassReflections();
        if (count($classes) !== 1) {
            return [];
        }
        $name = $node->var->name->name;
        $expected = $this->elements->property($classes[0], $name);
        return self::check($expected, $scope->getType($node->expr), $scope, 'waypoint.listOf', "Property {$classes[0]->getName()}::\$$name takes %s as #[ListOf] says, %s assigned.");
    }

    /** @return list<IdentifierRuleError> */
    private function constructed(Expr\New_ $node, Scope $scope): array
    {
        if (!$node->class instanceof Name || !$this->reflection->hasClass($scope->resolveName($node->class))) {
            return [];
        }
        $class = $this->reflection->getClass($scope->resolveName($node->class));
        $constructor = $class->getNativeReflection()->getConstructor();
        if ($constructor === null) {
            return [];
        }
        $parameters = $constructor->getParameters();
        $errors = [];
        foreach ($node->getArgs() as $position => $arg) {
            if ($arg->unpack) {
                break;
            }
            $parameter = null;
            foreach ($parameters as $candidate) {
                if ($arg->name !== null ? $candidate->getName() === $arg->name->name : $candidate->getPosition() === $position) {
                    $parameter = $candidate;
                }
            }
            if ($parameter === null || !$parameter->isPromoted()) {
                continue;
            }
            $name = $parameter->getName();
            $expected = $this->elements->property($class, $name);
            array_push($errors, ...self::check($expected, $scope->getType($arg->value), $scope, 'waypoint.listOf', "Parameter \$$name of {$class->getName()}::__construct() takes %s as #[ListOf] says, %s given."));
        }
        return $errors;
    }

    /** @return list<IdentifierRuleError> */
    private static function check(?Type $expected, Type $actual, Scope $scope, string $identifier, string $message): array
    {
        if ($expected === null || $expected->accepts($actual, $scope->isDeclareStrictTypes())->yes()) {
            return [];
        }
        return [RuleErrorBuilder::message(sprintf($message, $expected->describe(VerbosityLevel::typeOnly()), $actual->describe(VerbosityLevel::typeOnly())))->identifier($identifier)->build()];
    }
}
