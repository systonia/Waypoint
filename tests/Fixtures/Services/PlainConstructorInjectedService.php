<?php

namespace Waypoint\Tests\Fixtures\Services;

use Waypoint\Attributes\Inject;

/**
 * Unlike ConstructorInjectedService, $dep here is a plain (non-promoted)
 * constructor parameter: PHP does not create a matching property for it, so
 * this is the only way to exercise App::discoverAllClasses()'s dedicated
 * constructor-parameter walk -- a promoted property's #[Inject] attribute
 * is visible on both the property and the parameter, so the property walk
 * always finds it first.
 */
class PlainConstructorInjectedService
{
    public ?AnotherService $captured;

    public function __construct(#[Inject] ?AnotherService $dep = null)
    {
        // Never actually resolved/passed by the framework today (see
        // AppBootstrapTest) -- $dep is always null via its own default.
        $this->captured = $dep;
    }
}
