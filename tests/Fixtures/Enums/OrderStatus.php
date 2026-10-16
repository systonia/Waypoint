<?php

namespace Waypoint\Tests\Fixtures\Enums;

enum OrderStatus: string
{
    case Open = 'open';
    case Done = 'done';
}
