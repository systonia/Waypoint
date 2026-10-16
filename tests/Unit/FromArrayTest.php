<?php

namespace Waypoint\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Waypoint\Tests\Fixtures\Support\FromArrayDTO;
use Waypoint\Tests\Fixtures\Support\FromArrayInternalsDTO;

final class FromArrayTest extends TestCase
{
    public function testHydratesDeclaredPropertiesFromData(): void
    {
        $dto = new FromArrayDTO(['name' => 'Ada', 'age' => 42]);

        $this->assertSame('Ada', $dto->name);
        $this->assertSame(42, $dto->age);
    }

    public function testDefaultsToEmptyDataLeavingDefaultsInPlace(): void
    {
        $dto = new FromArrayDTO();

        $this->assertSame('', $dto->name);
        $this->assertSame(0, $dto->age);
    }

    public function testErrorsAndIsValidApplyTheRuleAttributesOnEveryRead(): void
    {
        $dto = new FromArrayDTO(['role' => 'root']);

        $this->assertFalse($dto->isValid);
        $this->assertSame(['name' => 'This value should not be blank.', 'role' => 'This value should be one of: guest, user.'], $dto->errors);

        $dto->name = 'Ada';
        $dto->role = 'user';
        $this->assertTrue($dto->isValid);
        $this->assertSame([], $dto->errors);
    }

    public function testFailAddsAFailureTheAttributesCannotKnowAboutAndTheLastOnePerFieldWins(): void
    {
        $dto = new FromArrayDTO(['name' => 'Ada']);
        $this->assertTrue($dto->isValid);

        $this->assertSame($dto, $dto->fail('name', 'Taken.')->fail('name', 'Really taken.'));
        $this->assertFalse($dto->isValid);
        $this->assertSame(['name' => 'Really taken.'], $dto->errors);
    }

    public function testInputCannotTouchTheValidationProperties(): void
    {
        $dto = new FromArrayDTO(['name' => 'Ada', 'errors' => ['x' => 'y'], 'isValid' => false, 'failures' => ['x' => 'y']]);

        $this->assertTrue($dto->isValid);
        $this->assertSame([], $dto->errors);
    }

    public function testUnknownKeysAreSilentlyIgnored(): void
    {
        $dto = new FromArrayDTO(['name' => 'Ada', 'doesNotExist' => 'x']);

        $this->assertSame('Ada', $dto->name);
    }

    public function testOnlyPubliclyWritableInstancePropertiesAreHydrated(): void
    {
        // The data is the request body: a DTO's private/protected state or restricted-set properties are not input.
        $dto = new FromArrayInternalsDTO(['name' => 'Ada', 'isAdmin' => true, 'credits' => 9999, 'owner' => 'attacker', 'verified' => true, 'shared' => 'attacker']);

        $this->assertSame('Ada', $dto->name);
        $this->assertSame(['isAdmin' => false, 'credits' => 0], $dto->internals());
        $this->assertSame('server', $dto->owner);
        $this->assertFalse($dto->verified);
        $this->assertSame('static', FromArrayInternalsDTO::$shared);
    }

    public function testExcludedPropertiesAreNeverAssignedEvenWhenPresentInData(): void
    {
        $dto = new FromArrayDTO(['excluded' => 'attacker-controlled']);

        $this->assertSame('default', $dto->excluded);
    }

    /**
     * A client sending JSON `null` for a non-nullable `string` property
     * (or any other value PHP's own type check rejects) must not crash
     * the request; it is left at its default instead, so #[NotBlank]/
     * Validator can catch the resulting empty value as a normal 422
     * rather than an uncaught TypeError becoming a 500. Regression test
     * for exactly that: Gaiden's TwoFactorLoginDTO 500'd on a null
     * challengeToken before this guard existed.
     */
    public function testAValueThatDoesNotFitThePropertysTypeIsSkippedNotThrown(): void
    {
        $dto = new FromArrayDTO(['name' => null, 'age' => 42]);

        $this->assertSame('', $dto->name);
        $this->assertSame(42, $dto->age);
    }

    public function testAWrongScalarTypeIsAlsoSkippedNotThrown(): void
    {
        $dto = new FromArrayDTO(['age' => ['not' => 'an int']]);

        $this->assertSame(0, $dto->age);
    }

    public function testJsonValuesOfAnotherTypeAreRefusedNotCast(): void
    {
        $dto = new \Waypoint\Tests\Fixtures\Support\TypedFormDTO(['admin' => 'false', 'qty' => 2.7, 'name' => true, 'price' => 3]);

        $this->assertSame([false, 1, '', 3.0], [$dto->admin, $dto->qty, $dto->name, $dto->price]);
        // "false" reads as false, as a form's text would; the rest has no reading as their type
        $this->assertSame(['qty', 'name'], array_keys($dto->errors));
    }

    public function testAFormsTextForNumbersAndBooleansIsConvertedStrictly(): void
    {
        $dto = new \Waypoint\Tests\Fixtures\Support\TypedFormDTO(['admin' => 'on', 'qty' => '5', 'price' => '2.5']);
        $this->assertSame([true, 5, 2.5], [$dto->admin, $dto->qty, $dto->price]);
        $this->assertTrue($dto->isValid);

        $bad = new \Waypoint\Tests\Fixtures\Support\TypedFormDTO(['qty' => '5abc', 'admin' => 'maybe']);
        $this->assertSame([1, false], [$bad->qty, $bad->admin]);
        $this->assertSame(['qty', 'admin'], array_keys($bad->errors));
    }
}
