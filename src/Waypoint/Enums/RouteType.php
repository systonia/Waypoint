<?php

namespace Waypoint\Enums;

enum RouteType: string {
    case Unset = '';
    case Static = 'static';
    case Dynamic = 'dynamic';
    case Task = 'task';
}