<?php

namespace Waypoint\Plugin;

use ReflectionClass;
use ReflectionMethod;

/**
 * Turns a plugin's own route attributes into plain data at compile time. The
 * result is stored in the route plan under the plugin's name and handed back
 * to that plugin's Guard/ResponseHook per request -- never re-reflected.
 */
interface RouteAttributeCompiler
{
    /**
     * @param ReflectionClass<object> $controller
     * @return array<string, mixed> Empty when the route carries nothing of interest.
     */
    public function compile(ReflectionClass $controller, ReflectionMethod $method): array;
}
