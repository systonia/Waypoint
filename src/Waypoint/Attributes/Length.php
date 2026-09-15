<?php

namespace Waypoint\Attributes;

use Attribute;

/**
 *
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Length {
    /**
     * Undocumented function
     *
     * @param integer $min
     * @param integer $max
     */
    public function __construct(
        /**
         * Undocumented variable
         *
         * @var integer
         */
        public int $min = 0,
        /**
         * Undocumented variable
         *
         * @var integer
         */
        public int $max = PHP_INT_MAX,
    ) {}
}
