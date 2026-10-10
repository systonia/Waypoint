<?php

namespace Waypoint\Tests\Fixtures\Controllers;

use Waypoint\Attributes\{Controller, Get, Post, Body, Middleware};
use Waypoint\Tests\Fixtures\Middlewares\{AddHeaderMiddleware, BeforeAfterMiddleware, BodyDtoCaptureMiddleware, InjectingMiddleware, ShortCircuitMiddleware};
use Waypoint\Tests\Fixtures\Support\CallTracker;
use Waypoint\Tests\Fixtures\DTO\CreateProductDTO;

#[Controller('/middleware')]
class MiddlewareController
{
    #[Get('/stacked')]
    #[Middleware(AddHeaderMiddleware::class)]
    #[Middleware(InjectingMiddleware::class)]
    public function stacked(): array
    {
        CallTracker::record('controller');
        return ['ok' => true];
    }

    #[Get('/before-after')]
    #[Middleware(BeforeAfterMiddleware::class)]
    public function beforeAfter(): array
    {
        CallTracker::record('controller');
        return ['ok' => true];
    }

    #[Get('/blocked')]
    #[Middleware(ShortCircuitMiddleware::class)]
    public function blocked(): array
    {
        CallTracker::record('controller');
        return ['ok' => true];
    }

    // A union-typed parameter: Router::buildArgPlan() can't map it to a
    // single builtin type, so it falls back to 'Unknown' (always null).
    #[Get('/union-param')]
    public function unionParam(string|int $value): array
    {
        return ['value' => $value];
    }

    #[Post('/with-body-dto')]
    #[Middleware(BodyDtoCaptureMiddleware::class)]
    public function withBodyDto(#[Body] CreateProductDTO $product): array
    {
        CallTracker::record('controller');
        return ['name' => $product->name];
    }
}
