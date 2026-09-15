<?php

namespace Waypoint\Tests\Fixtures\Controllers;

use Waypoint\Attributes\{Controller, Get, Sunset};

/**
 * #[Sunset] at the class level -- every route below inherits it. PHP's own
 * native #[\Deprecated] (8.4+) cannot target a class at all (only
 * functions/methods -- attempting it is a fatal compile-time error), so
 * unlike #[Version]/#[Sunset] there's no "class-level native deprecation"
 * scenario to fixture here; see DeprecatedRoutesController for
 * method-level #[\Deprecated].
 */
#[Controller('/legacy2')]
#[Sunset(date: '2099-06-15')]
class DeprecatedClassController
{
    #[Get('/class-level')]
    public function classLevel(): array
    {
        return ['ok' => true];
    }
}
