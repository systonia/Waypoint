<?php

namespace Waypoint;

use Stringable;

/**
 * Markup a view prints as it is: `<?= $this->section('head') ?>` where section() returns Html. Views escape every
 * other value `<?= ?>` prints. Only for HTML that was built safely, such as another template's output; anything
 * from a user stays a string and is escaped.
 */
readonly class Html implements Stringable
{
    /** @param string $html Markup that is safe as it is: escape everything from a user before it goes in */
    public function __construct(private string $html)
    {
    }

    public function __toString(): string
    {
        return $this->html;
    }
}
