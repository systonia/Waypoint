<?php

namespace Waypoint\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Waypoint\Waypoint;
use Waypoint\Tests\Fixtures\Support\{FromArrayDTO, RedactableDTO};

final class WaypointTest extends TestCase
{
    public function testRedactSkipsTheComputedValidationProperties(): void
    {
        $this->assertSame(['name' => 'Ada', 'age' => 0, 'role' => null, 'excluded' => 'default'], Waypoint::redact(new FromArrayDTO(['name' => 'Ada'])));
    }

    public function testRedactLeavesOrdinaryPropertiesAsIs(): void
    {
        $dto = new RedactableDTO();
        $dto->username = 'ada';

        $this->assertSame('ada', Waypoint::redact($dto)['username']);
    }

    public function testRedactReplacesASensitivePropertyWithItsDefaultPlaceholder(): void
    {
        $dto = new RedactableDTO();
        $dto->password = 'hunter2';

        $this->assertSame('**redacted**', Waypoint::redact($dto)['password']);
    }

    public function testRedactUsesASensitivePropertysOwnCustomPlaceholder(): void
    {
        $dto = new RedactableDTO();
        $dto->apiKey = 'sk-real-key';

        $this->assertSame('***', Waypoint::redact($dto)['apiKey']);
    }

    public function testRedactReplacesAPiiPropertyWithItsPlaceholder(): void
    {
        $dto = new RedactableDTO();
        $dto->email = 'ada@example.com';

        $this->assertSame('**redacted**', Waypoint::redact($dto)['email']);
    }

    public function testRedactSkipsAnUninitializedTypedPropertyRatherThanFatalErroring(): void
    {
        $dto = new RedactableDTO();

        $this->assertArrayNotHasKey('uninitialized', Waypoint::redact($dto));
    }

    public function testRedactNeverExposesNonPublicProperties(): void
    {
        $dto = new RedactableDTO();

        $this->assertArrayNotHasKey('internal', Waypoint::redact($dto));
    }
}
