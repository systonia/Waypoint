<?php

namespace Waypoint\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Waypoint\Tests\Fixtures\Support\FromArrayDTO;

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

    public function testUnknownKeysAreSilentlyIgnored(): void
    {
        $dto = new FromArrayDTO(['name' => 'Ada', 'doesNotExist' => 'x']);

        $this->assertSame('Ada', $dto->name);
    }

    public function testExcludedPropertiesAreNeverAssignedEvenWhenPresentInData(): void
    {
        $dto = new FromArrayDTO(['excluded' => 'attacker-controlled']);

        $this->assertSame('default', $dto->excluded);
    }

    /**
     * A client sending JSON `null` for a non-nullable `string` property
     * (or any other value PHP's own type check rejects) must not crash
     * the request -- left at its default instead, so #[NotBlank]/
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
}
