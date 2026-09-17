<?php

namespace Waypoint;

use ReflectionClass;
use ReflectionProperty;
use Waypoint\Attributes\{NotBlank, Email, Length, Regex, OneOf, SameAs};
use Waypoint\Validation\Messages;

/**
 * Validates a DTO's public properties against #[NotBlank]/#[Email]/#[Length]/
 * #[Regex], #[OneOf], #[SameAs]. The attribute set per class is reflected once and cached, so
 * validating the same DTO class on every request costs no reflection.
 */
class Validator
{
    /** @var array<class-string, list<array{prop: ReflectionProperty, rules: list<NotBlank|Email|Length|Regex|OneOf|SameAs>}>> */
    private static array $rulesByClass = [];

    /** @return array<string, string> field => message (the last failing rule wins, in NotBlank, Email, Length, Regex, OneOf, SameAs order). */
    #[\NoDiscard('Ignoring the returned errors means validation never actually gets enforced.')]
    public function validate(object $dto): array
    {
        $errors = [];
        foreach (self::rulesFor($dto) as ['prop' => $prop, 'rules' => $rules]) {
            $name = $prop->getName();
            $value = $prop->isInitialized($dto) ? $prop->getValue($dto) : null;

            foreach ($rules as $rule) {
                $message = match (true) {
                    $rule instanceof NotBlank => self::isBlank($value) ? $this->message('This value should not be blank.') : null,
                    $rule instanceof Email => $value !== null && !filter_var($value, FILTER_VALIDATE_EMAIL) ? $this->message('This value is not a valid email address.') : null,
                    $rule instanceof Length => $this->lengthError($value, $rule),
                    $rule instanceof Regex => is_string($value) && !preg_match($rule->pattern, $value) ? $this->message('This value does not match the required format.') : null,
                    $rule instanceof OneOf => $value !== null && !in_array($value, $rule->values, true) ? $this->message('This value should be one of: {choices}.', ['choices' => implode(', ', array_map('strval', $rule->values))]) : null,
                    $rule instanceof SameAs => $value !== self::propertyValue($dto, $rule->property) ? $this->message('This value should match {field}.', ['field' => $rule->property]) : null,
                };
                if ($message !== null) {
                    $errors[$name] = $message;
                }
            }
        }
        return $errors;
    }

    /** @return list<array{prop: ReflectionProperty, rules: list<NotBlank|Email|Length|Regex|OneOf|SameAs>}> */
    private static function rulesFor(object $dto): array
    {
        $class = get_class($dto);
        if (isset(self::$rulesByClass[$class])) {
            return self::$rulesByClass[$class];
        }

        $entries = [];
        foreach ((new ReflectionClass($dto))->getProperties(ReflectionProperty::IS_PUBLIC) as $prop) {
            $rules = [];
            foreach ([NotBlank::class, Email::class, Length::class, Regex::class, OneOf::class, SameAs::class] as $attribute) {
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

    /** The named public property's value, null when it is uninitialized or absent. */
    private static function propertyValue(object $dto, string $property): mixed
    {
        return $dto->{$property} ?? null;
    }

    private static function isBlank(mixed $value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '') || (is_array($value) && $value === []);
    }

    private function lengthError(mixed $value, Length $rule): ?string
    {
        $len = is_string($value) ? mb_strlen($value) : 0;
        return match (true) {
            $len < $rule->min => $this->message('This value is too short. Minimum length is {min}.', ['min' => $rule->min]),
            $len > $rule->max => $this->message('This value is too long. Maximum length is {max}.', ['max' => $rule->max]),
            default => null,
        };
    }

    /**
     * Through the container-bound Messages (a plugin, e.g. i18n) when there is one, else the English text with its placeholders filled in.
     * @param array<string, scalar> $params
     */
    private function message(string $text, array $params = []): string
    {
        $container = Waypoint::getInstance()?->getContainer();
        if ($container !== null && $container->isRegistered(Messages::class)) {
            return $container->get(Messages::class)->translate($text, $params);
        }
        foreach ($params as $name => $value) {
            $text = str_replace('{' . $name . '}', (string) $value, $text);
        }
        return $text;
    }
}
