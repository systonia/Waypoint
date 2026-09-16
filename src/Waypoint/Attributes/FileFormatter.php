<?php

namespace Waypoint\Attributes;

use Attribute;

/** Sends the route's return value as a raw file/body (a path to read, or the content itself) instead of JSON. */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class FileFormatter
{
    public function __construct(
        public ?string $mimetype = null,
        public ?string $filename = null,
        public bool $download = false
    ) {
    }
}
