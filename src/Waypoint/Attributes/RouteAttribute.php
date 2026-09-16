<?php

namespace Waypoint\Attributes;

/** Contract of the HTTP-method route attributes (Get, Post, Put, Patch, Delete). */
interface RouteAttribute
{
    public function getHttpMethod(): string;
    public function getPath(): string;
}
