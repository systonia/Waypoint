<?php

namespace Waypoint\Tests\Unit\Attributes;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Waypoint\Attributes\{NotBlank, Email, Length, Regex, OneOf, SameAs};

class ValidationAttributes_Fixture
{
    #[NotBlank]
    public $blank;

    #[Email]
    public $email;

    #[Length]
    public $defaultLength;

    #[Length(min: 3, max: 5)]
    public $boundedLength;

    #[Regex(pattern: '/^\d+$/')]
    public $digitsOnly;

    #[OneOf(['a', 'b'])]
    public $choice;

    #[SameAs('choice')]
    public $confirm;
}

/**
 * These attributes only carry data; Waypoint\Validator is what applies the
 * rules (see Tests/Unit/ValidatorTest.php). Here we just confirm the shape
 * each attribute exposes to the validator.
 */
final class ValidationAttributesTest extends TestCase
{
    private function attributeOn(string $property, string $attributeClass): object
    {
        return (new ReflectionClass(ValidationAttributes_Fixture::class))
            ->getProperty($property)
            ->getAttributes($attributeClass)[0]
            ->newInstance();
    }

    public function testNotBlankIsInstantiable(): void
    {
        $this->assertInstanceOf(NotBlank::class, $this->attributeOn('blank', NotBlank::class));
    }

    public function testEmailIsInstantiable(): void
    {
        $this->assertInstanceOf(Email::class, $this->attributeOn('email', Email::class));
    }

    public function testLengthDefaultsToZeroAndPhpIntMax(): void
    {
        $attr = $this->attributeOn('defaultLength', Length::class);
        $this->assertSame(0, $attr->min);
        $this->assertSame(PHP_INT_MAX, $attr->max);
    }

    public function testLengthAcceptsExplicitBounds(): void
    {
        $attr = $this->attributeOn('boundedLength', Length::class);
        $this->assertSame(3, $attr->min);
        $this->assertSame(5, $attr->max);
    }

    public function testRegexStoresPattern(): void
    {
        $attr = $this->attributeOn('digitsOnly', Regex::class);
        $this->assertSame('/^\d+$/', $attr->pattern);
    }

    public function testOneOfStoresValuesAndSameAsThePropertyName(): void
    {
        $this->assertSame(['a', 'b'], $this->attributeOn('choice', OneOf::class)->values);
        $this->assertSame('choice', $this->attributeOn('confirm', SameAs::class)->property);
    }
}
