<?php

namespace Waypoint\Tests\Fixtures\Controllers;

use Waypoint\Attributes\{Controller, Get, Version};

/**
 * Class-level #[Version('v1')] is the default for every route below;
 * override() overrides it with its own #[Version('v2')] for that one route
 * only -- proving "method wins over class".
 */
#[Controller('/widgets')]
#[Version('v1')]
class VersionedWidgetsController
{
    #[Get('/list')]
    public function list(): array
    {
        return ['from' => 'class-default'];
    }

    #[Get('/override')]
    #[Version('v2')]
    public function override(): array
    {
        return ['from' => 'method-override'];
    }
}
