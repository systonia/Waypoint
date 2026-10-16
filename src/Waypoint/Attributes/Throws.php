<?php

namespace Waypoint\Attributes;

use Attribute;

/** Documents an error response (OpenAPI only): which exception, its HTTP status, and an optional description. */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Throws
{
    public function __construct(public string $exception, public int $status, public ?string $description = null)
    {
    }
}
