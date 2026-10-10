<?php

namespace Waypoint\Attributes;

use Attribute;

/** Sends the route's return value as a raw body instead of JSON: return an SplFileInfo to send a file from disk, a string to send that text. */
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
