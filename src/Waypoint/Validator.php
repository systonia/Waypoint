<?php

namespace Waypoint;

use ReflectionClass;
use ReflectionProperty;
use Waypoint\Attributes\{NotBlank, Email, Length, Regex};

/**
 * Undocumented class
 */
class Validator
{
    /**
     * Validates all properties of a DTO with validation attributes.
     * @param object $dto
     * @return array Errors: field => message
     */
    #[\NoDiscard('Ignoring the returned errors means validation never actually gets enforced.')]
    public function validate(object $dto): array
    {
        $errors = [];
        $rc = new ReflectionClass($dto);

        foreach ($rc->getProperties(ReflectionProperty::IS_PUBLIC) as $prop) {
            $name = $prop->getName();

            // ReflectionProperty::isInitialized() has existed since PHP
            // 7.4 -- always present given this framework's own >=8.5
            // floor, so no method_exists() guard is needed around it.
            $value = $prop->isInitialized($dto) ? $prop->getValue($dto) : null;

            #region #NotBlank
            foreach ($prop->getAttributes(NotBlank::class) as $attr) {
                if ($value === null || (is_string($value) && trim($value) === '') || (is_array($value) && count($value) === 0)) {
                    $errors[$name] = 'This value should not be blank.';
                }
            }
            #endregion

            #region Email
            foreach ($prop->getAttributes(Email::class) as $attr) {
                if ($value !== null && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[$name] = 'This value is not a valid email address.';
                }
            }
            #endregion

            #region Length
            foreach ($prop->getAttributes(Length::class) as $attr) {
                /** @var Length $inst */
                $inst = $attr->newInstance();
                $len = is_string($value) ? mb_strlen($value) : 0;
                if ($len < $inst->min) {
                    $errors[$name] = "This value is too short. Minimum length is {$inst->min}.";
                } elseif ($len > $inst->max) {
                    $errors[$name] = "This value is too long. Maximum length is {$inst->max}.";
                }
            }
            #endregion

            #region Regex
            foreach ($prop->getAttributes(Regex::class) as $attr) {
                /** @var Regex $inst */
                $inst = $attr->newInstance();
                if ($value !== null && !preg_match($inst->pattern, $value)) {
                    $errors[$name] = "This value does not match the required format.";
                }
            }
            #endregion
        }

        return $errors;
    }
}
