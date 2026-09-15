<?php

namespace Waypoint\Tests\Fixtures\Controllers;

use Waypoint\Attributes\{Controller, Get, Sunset};

/**
 * Native PHP #[\Deprecated] (8.4+, not a Waypoint attribute) at the
 * method level, paired with #[Sunset] -- RouteCompiler reads both via
 * Reflection and bakes them into the compiled plan (see
 * RouteCompilerVersioningTest, which inspects the plan directly rather
 * than dispatching, so this fixture's real PHP-level deprecation notice
 * is never actually triggered just by compiling it).
 */
#[Controller('/legacy')]
class DeprecatedRoutesController
{
    #[Get('/method-level')]
    #[\Deprecated]
    #[Sunset(date: '2099-12-31')]
    public function methodLevel(): array
    {
        return ['ok' => true];
    }

    #[Get('/not-deprecated')]
    public function notDeprecated(): array
    {
        return ['ok' => true];
    }
}
