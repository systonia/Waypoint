<?php

namespace Waypoint\Attributes;

use Attribute;

/**
 *
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Tags
{
    /**
     * Undocumented function
     *
     * @param string[] $tags
     * @param string|null $name
     */
    public function __construct(
        /**
         * @var string[]
         */
        public array $tags = [],

        public ?string $name = null
    ) {
    }
}
