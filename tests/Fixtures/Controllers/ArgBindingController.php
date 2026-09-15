<?php

namespace Waypoint\Tests\Fixtures\Controllers;

use stdClass;
use Waypoint\Attributes\{Controller, Get, Inject};
use Waypoint\Http\{Request, Response};
use Waypoint\Router;

/**
 * Exercises the argument/property binding paths that the "everyday"
 * fixtures never touch: Request/Response/Router injected as controller
 * *properties* (rather than method parameters), a plain type-hinted
 * Response method parameter (no attribute needed), an unattributed scalar
 * parameter bound implicitly by name, and an unrecognized class parameter
 * (which always binds to null).
 */
#[Controller('/argbinding')]
class ArgBindingController
{
    #[Inject]
    private Request $injectedRequest;

    #[Inject]
    private Response $injectedResponse;

    #[Inject]
    private Router $injectedRouter;

    #[Get('/props')]
    public function props(): array
    {
        return [
            'hasRequest' => isset($this->injectedRequest),
            'hasResponse' => isset($this->injectedResponse),
            'hasRouter' => isset($this->injectedRouter),
        ];
    }

    #[Get('/response-param')]
    public function responseParam(Response $res): array
    {
        $res->withHeader('X-From-Param', 'yes');
        return ['ok' => true];
    }

    #[Get('/scalar/{id}')]
    public function scalarBinding(int $id): array
    {
        return ['id' => $id, 'type' => gettype($id)];
    }

    #[Get('/unknown')]
    public function unknownBinding(?stdClass $thing): array
    {
        return ['thing' => $thing];
    }
}
