<?php

namespace Waypoint\Tests\Integration;

use Waypoint\Waypoint;
use Waypoint\Http\Request;
use Waypoint\Tests\Fixtures\Controllers\BodyBindingController;

final class BodyBindingTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Waypoint::create()->attach([BodyBindingController::class]);
    }

    public function testAPropertyWithoutADefaultIsRequired(): void
    {
        $res = $this->request('POST', '/body/required')->withJson(['qty' => 2])->send();

        $this->assertSame(422, $res->status());
        $this->assertSame(['name' => 'This value is required.'], $res->json()['errors'] ?? null);

        $this->request('POST', '/body/required')->withJson(['name' => 'Ada'])->send()->assertOk();
    }

    public function testAnArrayParameterGetsTheDecodedBody(): void
    {
        $this->request('POST', '/body/raw')->withJson(['a' => 1, 'b' => [2]])->send()->assertOk()->assertJson(['a' => 1, 'b' => [2]]);
    }

    public function testEnumsAndNestedDtosAreBuiltFromTheBody(): void
    {
        $this->request('POST', '/body/shipment')->withJson(['status' => 'done', 'to' => ['city' => 'Freiburg'], 'stops' => [['city' => 'Basel']]])->send()
            ->assertOk()->assertJson(['status' => 'done', 'to' => 'Freiburg', 'stops' => 1]);

        $res = $this->request('POST', '/body/shipment')->withJson(['status' => 'lost', 'to' => ['city' => ''], 'stops' => [['city' => '']]])->send();
        $this->assertSame(422, $res->status());
        $this->assertSame(['status', 'to.city', 'stops.0.city'], array_keys($res->json()['errors'] ?? []));
    }

    public function testAMalformedJsonBodyIsABadRequest(): void
    {
        $res = Waypoint::create()->handle(Request::fromParts('POST', '/body/required', ['Content-Type' => 'application/json'], '{"name":"a",'));

        $this->assertSame(400, $res->getStatus());
        $this->assertSame('Bad Request', json_decode($res->getBody(), true)['title'] ?? null);
    }
}
