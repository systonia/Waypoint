<?php

namespace Waypoint\Attributes;

/**
 * Shared contract for the HTTP-method route attributes (Get, Post, Put,
 * Patch, Delete) -- lets RouteCompiler::extractRouteAndFormatter() report a
 * precise type for "whichever one of these was found on a method" instead
 * of plain object, without changing which attribute wins.
 */
interface RouteAttribute
{
    public function getHttpMethod(): string;

    public function getPath(): string;
}
