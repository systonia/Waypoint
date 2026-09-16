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
}
