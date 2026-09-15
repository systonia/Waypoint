<?php

namespace Waypoint\Tests\Integration;

use Waypoint\Waypoint;
use Waypoint\OpenAPI\OpenAPIController;
use Waypoint\Tests\Fixtures\Controllers\{UsersV1Controller, UsersV2Controller};

final class OpenAPIVersioningControllerTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Waypoint::create()->attach([OpenAPIController::class, UsersV1Controller::class, UsersV2Controller::class]);
    }

    public function testSpecVersionRouteServesThatVersionsDocument(): void
    {
        $output = $this->dispatch('GET', '/openapi/spec.v1.json');
        $spec = json_decode($output, true);

        $this->assertArrayHasKey('/v1/users', $spec['paths']);
        $this->assertArrayNotHasKey('/v2/users', $spec['paths']);
    }

    public function testDifferentVersionsResolveToDifferentDocuments(): void
    {
        $v1 = json_decode($this->dispatch('GET', '/openapi/spec.v1.json'), true);
        $v2 = json_decode($this->dispatch('GET', '/openapi/spec.v2.json'), true);

        $this->assertArrayHasKey('/v1/users', $v1['paths']);
        $this->assertArrayHasKey('/v2/users', $v2['paths']);
        $this->assertArrayNotHasKey('/v2/users', $v1['paths']);
        $this->assertArrayNotHasKey('/v1/users', $v2['paths']);
    }

    public function testARequestForAVersionThatWasNeverCompiledIs404(): void
    {
        $output = $this->dispatch('GET', '/openapi/spec.v99.json');

        $this->assertSame(
            ['error' => "OpenAPI spec for version 'v99' not found."],
            json_decode($output, true)
        );
    }

    public function testTheCombinedSpecJsonStillResolvesAtItsOwnFixedPath(): void
    {
        $spec = json_decode($this->dispatch('GET', '/openapi/spec.json'), true);

        $this->assertArrayHasKey('/v2/users', $spec['paths']);
        $this->assertArrayNotHasKey('/v1/users', $spec['paths']);
    }
}
