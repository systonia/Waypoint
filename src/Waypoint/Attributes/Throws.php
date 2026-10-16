<?php

namespace Waypoint\Attributes;

use Attribute;

/**
 * Documents an error response (OpenAPI only): which exception, its HTTP status, and an optional description.
 * Pass the arguments by name (`exception:`, `status:`, `description:`): the generator reads them by name.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Throws
{
    public function __construct(public string $exception, public int $status, public ?string $description = null)
    {
    }
}
