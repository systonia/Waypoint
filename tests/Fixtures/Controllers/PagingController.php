<?php

namespace Waypoint\Tests\Fixtures\Controllers;

use Waypoint\Attributes\{Controller, Get, Query};

/** Scalars from the query string, with defaults and attributes */
#[Controller('/paging')]
class PagingController
{
    #[Get]
    public function index(?int $page = 5, int $size = 20, ?string $sort = null): array
    {
        return ['page' => $page, 'size' => $size, 'sort' => $sort];
    }

    #[Get('/n')]
    public function n(#[Query] int $n): array
    {
        return ['n' => $n];
    }
}
