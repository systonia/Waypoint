<?php

namespace Waypoint\Attributes;

use Attribute;

/**
 * Documents what a route method answers with in generated OpenAPI: the success response's status, description and
 * schema. $type is a class, an enum or a scalar keyword (int, string, float, bool); $list makes it a list of $type,
 * $map an object of $type values by string key. A nullable return type still allows null. It wins over the return
 * type, so it is also how an `array` return type gets its elements.
 */
#[Attribute(Attribute::TARGET_METHOD)]
class Returns
{
    public function __construct(
        public string $type,
        public bool $list = false,
        public bool $map = false,
        public ?string $description = null,
        public int $status = 200,
    ) {
    }
}
