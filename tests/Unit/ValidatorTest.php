<?php

namespace Waypoint\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Waypoint\Validator;
use Waypoint\Attributes\{NotBlank, Email, Length, Regex, OneOf, SameAs};

class ValidatorTest_ContactDTO
{
    #[NotBlank]
    public $name;

    #[Email]
    public $email;
}

class ValidatorTest_AccountDTO
{
    #[Length(min: 3, max: 5)]
    public $username;

    #[Regex(pattern: '/^\d{4}$/')]
    public $pin;
}

class ValidatorTest_MultiRuleDTO
{
    #[NotBlank]
    #[Length(min: 3, max: 5)]
    public $code;
}

class ValidatorTest_ChoiceDTO
{
    #[OneOf(['guest', 'user', 'admin'])]
    public $role;

    #[OneOf([1, 2])]
    public $level;

    public $password;

    #[SameAs('password')]
    public $passwordConfirm;
}

class ValidatorTest_UninitializedTypedPropertyDTO
{
    // Typed, no default: PHP leaves this genuinely uninitialized until
    // assigned, unlike untyped/nullable properties which default to null.
    #[NotBlank]
    public string $name;
}

final class ValidatorTest extends TestCase
{
    private Validator $validator;

    protected function setUp(): void
    {
        $this->validator = new Validator();
    }

    public function testNotBlankRejectsNullEmptyAndWhitespaceOnly(): void
    {
        foreach ([null, '', '   '] as $value) {
            $dto = new ValidatorTest_ContactDTO();
            $dto->name = $value;
            $errors = $this->validator->validate($dto);
            $this->assertArrayHasKey('name', $errors, 'Expected blank rejection for ' . var_export($value, true));
        }
    }

    public function testNotBlankAcceptsANonEmptyValue(): void
    {
        $dto = new ValidatorTest_ContactDTO();
        $dto->name = 'value';
        $errors = $this->validator->validate($dto);
        $this->assertArrayNotHasKey('name', $errors);
    }

    public function testEmailRejectsInvalidAddresses(): void
    {
        $dto = new ValidatorTest_ContactDTO();
        $dto->email = 'not-an-email';
        $errors = $this->validator->validate($dto);
        $this->assertEquals('This value is not a valid email address.', $errors['email']);
    }

    public function testEmailAcceptsAValidAddress(): void
    {
        $dto = new ValidatorTest_ContactDTO();
        $dto->email = 'user@example.com';
        $errors = $this->validator->validate($dto);
        $this->assertArrayNotHasKey('email', $errors);
    }

    public function testEmailSkipsValidationWhenNull(): void
    {
        $dto = new ValidatorTest_ContactDTO();
        $dto->email = null;
        $errors = $this->validator->validate($dto);
        $this->assertArrayNotHasKey('email', $errors);
    }

    public function testLengthRejectsTooShort(): void
    {
        $dto = new ValidatorTest_AccountDTO();
        $dto->username = 'ab';
        $errors = $this->validator->validate($dto);
        $this->assertStringContainsString('too short', $errors['username']);
    }

    public function testLengthRejectsTooLong(): void
    {
        $dto = new ValidatorTest_AccountDTO();
        $dto->username = 'abcdef';
        $errors = $this->validator->validate($dto);
        $this->assertStringContainsString('too long', $errors['username']);
    }

    public function testLengthAcceptsWithinBounds(): void
    {
        $dto = new ValidatorTest_AccountDTO();
        $dto->username = 'abcd';
        $errors = $this->validator->validate($dto);
        $this->assertArrayNotHasKey('username', $errors);
    }

    public function testRegexRejectsNonMatchingValue(): void
    {
        $dto = new ValidatorTest_AccountDTO();
        $dto->pin = '123';
        $errors = $this->validator->validate($dto);
        $this->assertStringContainsString('does not match', $errors['pin']);
    }

    public function testRegexAcceptsMatchingValue(): void
    {
        $dto = new ValidatorTest_AccountDTO();
        $dto->pin = '1234';
        $errors = $this->validator->validate($dto);
        $this->assertArrayNotHasKey('pin', $errors);
    }

    public function testValidateCollectsOneErrorPerInvalidField(): void
    {
        $dto = new ValidatorTest_ContactDTO();
        $dto->name = '';
        $dto->email = 'invalid';

        $errors = $this->validator->validate($dto);

        $this->assertArrayHasKey('name', $errors);
        $this->assertArrayHasKey('email', $errors);
    }

    public function testValidateReturnsEmptyArrayWhenEverythingIsValid(): void
    {
        $dto = new ValidatorTest_ContactDTO();
        $dto->name = 'not blank';
        $dto->email = 'me@a.com';

        $this->assertEmpty($this->validator->validate($dto));
    }

    public function testOneOfRejectsAValueOutsideTheListAndSkipsNull(): void
    {
        $dto = new ValidatorTest_ChoiceDTO();
        $dto->role = 'root';
        $dto->level = '1'; // strict: the string '1' is not the int 1

        $errors = $this->validator->validate($dto);

        $this->assertSame('This value should be one of: guest, user, admin.', $errors['role']);
        $this->assertSame('This value should be one of: 1, 2.', $errors['level']);

        $dto->role = null;
        $dto->level = 2;
        $this->assertArrayNotHasKey('role', $this->validator->validate($dto));
        $this->assertArrayNotHasKey('level', $this->validator->validate($dto));
    }

    public function testSameAsComparesAgainstTheNamedProperty(): void
    {
        $dto = new ValidatorTest_ChoiceDTO();
        $dto->password = 'secret';
        $dto->passwordConfirm = 'secrets';

        $this->assertSame('This value should match password.', $this->validator->validate($dto)['passwordConfirm']);

        $dto->passwordConfirm = 'secret';
        $this->assertArrayNotHasKey('passwordConfirm', $this->validator->validate($dto));
    }

    public function testUninitializedTypedPropertyIsTreatedAsBlank(): void
    {
        $dto = new ValidatorTest_UninitializedTypedPropertyDTO();

        $errors = $this->validator->validate($dto);

        $this->assertArrayHasKey('name', $errors);
    }

    public function testLastMatchingRuleOnTheSameFieldWinsWhenMultipleFail(): void
    {
        // Waypoint\Validator keeps one message per field (a plain string, not
        // an accumulated list): it checks NotBlank, then Email, then Length,
        // then Regex, in that fixed order, so when several rules on the
        // same property fail, whichever is checked last overwrites earlier
        // messages -- here Length (checked after NotBlank) wins.
        $dto = new ValidatorTest_MultiRuleDTO();
        $dto->code = ''; // fails both #[NotBlank] and #[Length(min:3)]

        $errors = $this->validator->validate($dto);

        $this->assertArrayHasKey('code', $errors);
        $this->assertIsString($errors['code']);
        $this->assertStringContainsString('too short', $errors['code']);
    }
}
