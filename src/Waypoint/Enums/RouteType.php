<?php

namespace Waypoint\Enums;

/** The kind of a compiled plan Router::addCompiledRoute() stores: a static path, a path with placeholders, or a CLI task. */
enum RouteType: string {
    case Unset = '';
    case Static = 'static';
    case Dynamic = 'dynamic';
    case Task = 'task';
}