<?php

namespace Waypoint\Attributes;

use Attribute;

/** Marks a class as a CLI task manager, with an optional name prefix ("prefix:task") for every #[Task] on it. */
#[Attribute(Attribute::TARGET_CLASS)]
class Manager
{
    private string $name;

    public function __construct(?string $name = null)
    {
        $this->name = trim($name ?? '', '/');
    }

    /** The prefix, or '' when none. */
    public function getName(): string
    {
        return $this->name;
    }
}
