<?php

namespace Waypoint\Attributes;

use Attribute;

/**
 * Documents a DTO property in generated OpenAPI schemas. Purely descriptive
 * metadata read by Waypoint\OpenAPI\OpenAPIGenerator -- it has no effect on
 * request binding or validation.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Property
{
    public function __construct(
        public ?string $description = null,
        public ?string $format = null,
        public mixed $example = null,
        public bool $deprecated = false,
    ) {
    }
}
