<?php

namespace Waypoint\Attributes;

/** Shared body of #[Get]/#[Post]/#[Put]/#[Patch]/#[Delete]: a path pattern such as '/users/{id}'. */
abstract class HttpRoute implements RouteAttribute
{
    public function __construct(private string $path = '')
    {
    }

    public function getPath(): string
    {
        return $this->path;
    }
}
