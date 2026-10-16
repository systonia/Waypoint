<?php

namespace Waypoint\Attributes;

use Attribute;

/**
 * Documents an error response (OpenAPI only): which exception, its HTTP status, and an optional description.
 * Without a status, an HttpException's own default code is used (404 for NotFoundException), 500 for any other.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Throws
{
    public function __construct(public string $exception, public ?int $status = null, public ?string $description = null)
    {
    }
}
