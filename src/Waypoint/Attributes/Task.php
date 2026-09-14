<?php

namespace Waypoint\Attributes;

use Attribute;

/**
 *
 */
#[Attribute(Attribute::TARGET_METHOD)]
class Task
{
        /**
     * Undocumented variable
     *
     * @var string|null
     */
    private ?string $name;

    /**
     * Undocumented function
     *
     * @param string|null $name Optional prefix name for this manager, e.g. 'hello_'
     */
    public function __construct(?string $name = null)
    {
        // Normalize: ensure leading slash, no trailing slash (or null)
        if ($name === null || $name === '') {
            $this->name = null;
        } else {
            $trimmed = trim($name, '/');
            $this->name = $trimmed;
        }
    }

    /**
     * Get the configured manager name prefix (e.g. 'hello_').
     * Returns empty string if none.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name ?? '';
    }
}
