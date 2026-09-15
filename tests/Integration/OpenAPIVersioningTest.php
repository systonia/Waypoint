<?php

namespace Waypoint\Tests\Integration;

use Waypoint\Waypoint;
use Waypoint\OpenAPI\OpenAPIGenerator;
use Waypoint\Tests\Fixtures\Controllers\{CustomersController, UsersV1Controller, UsersV2Controller, UsersV10Controller};

/**
 * The multi-file spec.json (combined, "latest version wins" per group) /
 * spec.vX.json (exact version) split -- OpenAPIController is the one that
 * actually serves these as separate HTTP routes (see
 * OpenAPIVersioningControllerTest); this exercises OpenAPIGenerator's own
 * generate($version)/hasVersion() directly.
 */
final class OpenAPIVersioningTest extends IntegrationTestCase
{
    private function generator(array $controllers): OpenAPIGenerator
    {
        $app = Waypoint::create();
        $app->attach($controllers);
        return new OpenAPIGenerator($app->getRouter());
    }

    public function testWithNoVersionedRoutesAtAllGenerateBehavesExactlyLikeBefore(): void
    {
        $spec = $this->generator([CustomersController::class])->generate();

        $this->assertArrayHasKey('/customers', $spec['paths']);
        $this->assertArrayHasKey('/customers/{customerId}/orders', $spec['paths']);
    }

    public function testGeneratingForAnExactVersionReturnsOnlyThatVersionsRoutes(): void
    {
        $generator = $this->generator([
            UsersV1Controller::class,
            UsersV2Controller::class,
            UsersV10Controller::class,
        ]);

        $spec = $generator->generate('v1');

        $this->assertArrayHasKey('/v1/users', $spec['paths']);
        $this->assertArrayNotHasKey('/v2/users', $spec['paths']);
        $this->assertArrayNotHasKey('/v10/users', $spec['paths']);
    }

    public function testEachVersionsSpecContainsExactlyItsOwnRoute(): void
    {
        $generator = $this->generator([
            UsersV1Controller::class,
            UsersV2Controller::class,
            UsersV10Controller::class,
        ]);

        $this->assertSame(['/v2/users'], array_keys($generator->generate('v2')['paths']));
        $this->assertSame(['/v10/users'], array_keys($generator->generate('v10')['paths']));
    }

    public function testTheCombinedSpecKeepsOnlyTheNumericallyHighestVersionPerGroup(): void
    {
        // v10 must win over v2 -- a plain string comparison ('v10' <=> 'v2')
        // would pick 'v2', since '1' < '2' character-by-character; this is
        // exactly the bug semantic version comparison avoids.
        $generator = $this->generator([
            UsersV1Controller::class,
            UsersV2Controller::class,
            UsersV10Controller::class,
        ]);

        $spec = $generator->generate();

        $this->assertArrayHasKey('/v10/users', $spec['paths']);
        $this->assertArrayNotHasKey('/v1/users', $spec['paths']);
        $this->assertArrayNotHasKey('/v2/users', $spec['paths']);
    }

    public function testTheCombinedSpecStillIncludesUnversionedRoutesAlongsideVersionedOnes(): void
    {
        $generator = $this->generator([CustomersController::class, UsersV1Controller::class]);

        $spec = $generator->generate();

        $this->assertArrayHasKey('/customers', $spec['paths']);
        $this->assertArrayHasKey('/v1/users', $spec['paths']);
    }

    public function testHasVersionReflectsExactlyWhatWasCompiled(): void
    {
        $generator = $this->generator([UsersV1Controller::class]);

        $this->assertTrue($generator->hasVersion('v1'));
        $this->assertFalse($generator->hasVersion('v2'));
        $this->assertFalse($generator->hasVersion('v99'));
    }
}
