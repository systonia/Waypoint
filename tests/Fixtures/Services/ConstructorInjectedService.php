<?php

namespace Waypoint\Tests\Fixtures\Services;

use Waypoint\Attributes\Inject;

/**
 * $example is constructed as null (App::initDependencyInjection() always
 * calls `new $cls()` with zero arguments -- constructor arguments
 * themselves are never resolved/passed), but it IS a real property (a
 * promoted constructor property is visible on both the parameter and
 * property reflection surfaces), so App's post-construction #[Inject]
 * property-wiring pass finds and resolves it same as any other property.
 * See PlainConstructorInjectedService for the one case that's still just
 * discovered, never actually resolved: a plain, non-promoted parameter,
 * which PHP creates no matching property for at all.
 */
class ConstructorInjectedService
{
    public function __construct(#[Inject] public ?ExampleService $example = null)
    {
    }
}
