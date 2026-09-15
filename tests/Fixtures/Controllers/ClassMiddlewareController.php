<?php

namespace Waypoint\Tests\Fixtures\Controllers;

use Waypoint\Attributes\{Controller, Get, Middleware};
use Waypoint\Tests\Fixtures\Middlewares\{AddHeaderMiddleware, ClassLevelMiddleware};
use Waypoint\Tests\Fixtures\Support\CallTracker;

#[Controller('/class-middleware')]
#[Middleware(ClassLevelMiddleware::class)]
class ClassMiddlewareController
{
    #[Get('/plain')]
    public function plain(): array
    {
        CallTracker::record('controller');
        return ['ok' => true];
    }

    #[Get('/stacked')]
    #[Middleware(AddHeaderMiddleware::class)]
    public function stacked(): array
    {
        CallTracker::record('controller');
        return ['ok' => true];
    }
}
