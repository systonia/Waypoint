<?php

namespace Waypoint\Attributes;

use Attribute;

/** Marks a #[Manager] method as a CLI task, invoked as `php index.php [prefix:]name`. */
#[Attribute(Attribute::TARGET_METHOD)]
class Task
{
    private string $name;

    public function __construct(?string $name = null)
    {
        $this->name = trim($name ?? '', '/');
    }

    /** The task name, or '' when none (such a task is skipped). */
    public function getName(): string
    {
        return $this->name;
    }
}
