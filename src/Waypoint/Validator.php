<?php

namespace Waypoint;

use ReflectionClass;
use ReflectionProperty;
use Waypoint\Attributes\{NotBlank, Email, Length, Regex};

/**
 * Validates a DTO's public properties against #[NotBlank]/#[Email]/#[Length]/
 * #[Regex]. The attribute set per class is reflected once and cached, so
 * validating the same DTO class on every request costs no reflection.
 */
class Validator
{
    /** @var array<class-string, list<array{prop: ReflectionProperty, rules: list<NotBlank|Email|Length|Regex>}>> */
    private static array $rulesByClass = [];

    /** @return array<string, string> field => message (the last failing rule wins, in NotBlank, Email, Length, Regex order). */
    #[\NoDiscard('Ignoring the returned errors means validation never actually gets enforced.')]
    public function validate(object $dto): array
    {
        $errors = [];
        foreach (self::rulesFor($dto) as ['prop' => $prop, 'rules' => $rules]) {
            $name = $prop->getName();
            $value = $prop->isInitialized($dto) ? $prop->getValue($dto) : null;

            foreach ($rules as $rule) {
                $message = match (true) {
                    $rule instanceof NotBlank => self::isBlank($value) ? 'This value should not be blank.' : null,
                    $rule instanceof Email => $value !== null && !filter_var($value, FILTER_VALIDATE_EMAIL) ? 'This value is not a valid email address.' : null,
                    $rule instanceof Length => self::lengthError($value, $rule),
                    $rule instanceof Regex => is_string($value) && !preg_match($rule->pattern, $value) ? 'This value does not match the required format.' : null,
                };
                if ($message !== null) {
                    $errors[$name] = $message;
                }
            }
        }
        return $errors;
    }

    /** @return list<array{prop: ReflectionProperty, rules: list<NotBlank|Email|Length|Regex>}> */
    private static function rulesFor(object $dto): array
    {
        $class = get_class($dto);
        if (isset(self::$rulesByClass[$class])) {
            return self::$rulesByClass[$class];
        }

        $entries = [];
        foreach ((new ReflectionClass($dto))->getProperties(ReflectionProperty::IS_PUBLIC) as $prop) {
            $rules = [];
            foreach ([NotBlank::class, Email::class, Length::class, Regex::class] as $attribute) {
                foreach ($prop->getAttributes($attribute) as $attr) {
                    $rules[] = $attr->newInstance();
                }
            }
            if ($rules !== []) {
                $entries[] = ['prop' => $prop, 'rules' => $rules];
            }
        }
        return self::$rulesByClass[$class] = $entries;
    }

    private static function isBlank(mixed $value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '') || (is_array($value) && $value === []);
    }

    private static function lengthError(mixed $value, Length $rule): ?string
    {
        $len = is_string($value) ? mb_strlen($value) : 0;
        return match (true) {
            $len < $rule->min => "This value is too short. Minimum length is {$rule->min}.",
            $len > $rule->max => "This value is too long. Maximum length is {$rule->max}.",
            default => null,
        };
    }
}
