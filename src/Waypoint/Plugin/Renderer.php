<?php

namespace Waypoint\Plugin;

use Waypoint\Http\{Request, Response};

/** Renders a plugin's own return type. Asked after View and Redirect, before the core JSON/XML/file renderer. */
interface Renderer
{
    public function supports(mixed $result): bool;

    public function render(mixed $result, Request $req, Response $res): void;
}
