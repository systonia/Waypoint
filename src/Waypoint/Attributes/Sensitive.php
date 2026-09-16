<?php

namespace Waypoint\Attributes;

use Attribute;

/**
 * Marks a DTO property as sensitive -- a credential, secret, or token
 * whose real value must never end up in a log line, error report, or any
 * other place `Waypoint::redact()` is used to build a safe-to-display
 * copy of an object. See Waypoint::redact() for how this is actually
 * enforced; the attribute itself does nothing on its own (same as every
 * other purely-descriptive attribute in this codebase, e.g. #[Property]).
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Sensitive
{
    public function __construct(
        public string $placeholder = '**redacted**',
    ) {
    }
}
