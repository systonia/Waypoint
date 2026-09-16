<?php

namespace Waypoint\Tests\Fixtures\Controllers;

use Waypoint\Attributes\{Controller, Get, Post, Body, Middleware};
use Waypoint\Tests\Fixtures\Middlewares\{AddHeaderMiddleware, BeforeAfterMiddleware, BodyDtoCaptureMiddleware, InjectingMiddleware, NotAMiddlewareBase, ShortCircuitMiddleware};
use Waypoint\Tests\Fixtures\Support\CallTracker;
use Waypoint\Tests\Fixtures\DTO\CreateProductDTO;

#[Controller('/middleware')]
class MiddlewareController
{
    // Bare class name -- defaults to calling handle().
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

    // The middleware class doesn't exist -- Router::collectMiddlewares()
    // must skip it at compile time rather than crash the whole app boot.
    #[Get('/bad-middleware')]
    #[Middleware(['Totally\\Fake\\MiddlewareClass', 'handle'])]
    public function badMiddleware(): array
    {
        return ['ok' => true];
    }

    // A real, existing class -- just not a MiddlewareBase subclass. Must
    // be rejected at compile time exactly like the nonexistent class
    // above, now that MiddlewareBase is mandatory.
    #[Get('/not-middleware-base')]
    #[Middleware(NotAMiddlewareBase::class)]
    public function notMiddlewareBase(): array
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
