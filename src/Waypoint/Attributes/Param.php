<?php

namespace Waypoint\Attributes;

use Attribute;

/**
 * Binds a route placeholder to a controller method parameter, e.g.
 * #[Get('/{customerId}')] + function bla(#[Param] string $customerId).
 * By default the parameter name is matched against the placeholder name;
 * pass $name to bind to a differently-named placeholder.
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
class Param
{
    public function __construct(
        /**
         * Placeholder name to bind, if different from the parameter's own name.
         *
         * @var string|null
         */
        public ?string $name = null
    ) {
    }
}
