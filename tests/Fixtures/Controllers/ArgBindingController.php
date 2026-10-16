<?php

namespace Waypoint\Tests\Fixtures\Controllers;

use stdClass;
use Waypoint\Attributes\{Controller, Get, Inject, Query};
use Waypoint\Tests\Fixtures\Enums\{OrderStatus, Priority};
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

    #[Get('/ip')]
    public function ip(Request $req): array
    {
        return ['ip' => $req->ip];
    }

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

    #[Get('/name/{name}')]
    public function nameBinding(string $name): array
    {
        return ['name' => $name];
    }

    #[Get('/file/{name}.json')]
    public function fileBinding(string $name): array
    {
        return ['file' => $name];
    }

    #[Get('/tag/c++/{name}')]
    public function hashBinding(string $name): array
    {
        return ['tag' => $name];
    }

    #[Get('/typed')]
    public function typedQuery(bool $active, int $page, float $ratio): array
    {
        return ['active' => $active, 'page' => $page, 'ratio' => $ratio];
    }

    #[Get('/enum')]
    public function enumQuery(#[Query] OrderStatus $status, Priority $priority = Priority::Low): array
    {
        return ['status' => $status->value, 'priority' => $priority->value];
    }

    #[Get('/enum/{status}')]
    public function enumPath(OrderStatus $status): array
    {
        return ['status' => $status->value];
    }

    #[Get('/unknown')]
    public function unknownBinding(?stdClass $thing): array
    {
        return ['thing' => $thing];
    }
}
