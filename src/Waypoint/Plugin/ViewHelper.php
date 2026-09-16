<?php

namespace Waypoint\Plugin;

/** Methods available inside templates as `$this->name(...)`. Two plugins offering the same name is a boot error. */
interface ViewHelper
{
    /** @return array<string, callable> name => callable */
    public function helpers(): array;
}
