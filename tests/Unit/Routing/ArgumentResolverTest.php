<?php

namespace Waypoint\Tests\Unit\Routing;

use PHPUnit\Framework\TestCase;
use Waypoint\Attributes\NotBlank;
use Waypoint\Exceptions\ValidationException;
use Waypoint\Http\{Request, Response};
use Waypoint\Routing\ArgumentResolver;

final class ArgumentResolverTest extends TestCase
{
    public function testADtoOfItsOwnWithAnErrorsPropertyIsStillValidated(): void
    {
        $dto = new class ([]) {
            #[NotBlank]
            public string $title = '';

            public array $errors = [];

            public function __construct(array $data)
            {
                foreach ($data as $key => $value) {
                    $this->$key = $value;
                }
            }
        };
        $req = Request::fromParts('POST', '/', ['Content-Type' => 'application/json'], '{"errors":[]}');

        foreach (['Body', 'BodyCollection'] as $inject) {
            if ($inject === 'BodyCollection') {
                $req->body = [['errors' => []]];
            }
            try {
                (new ArgumentResolver())->resolve([['inject' => $inject, 'class' => $dto::class, 'validate' => true]], $req, new Response(), []);
                $this->fail("$inject was not validated");
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
