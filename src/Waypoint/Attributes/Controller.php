<?php

namespace Waypoint\Attributes;

use Attribute;

/** Marks a class as an HTTP controller, with an optional path prefix for every route on it (e.g. '/users'). */
#[Attribute(Attribute::TARGET_CLASS)]
class Controller
{
    private string $path;

    public function __construct(?string $path = null)
    {
        $trimmed = trim($path ?? '', '/');
        $this->path = $trimmed === '' ? '' : '/' . $trimmed;
    }

    /** '/prefix' or '' when none. */
    public function getPath(): string
    {
        return $this->path;
    }
}
