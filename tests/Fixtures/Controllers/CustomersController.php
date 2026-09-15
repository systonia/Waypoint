<?php

namespace Waypoint\Tests\Fixtures\Controllers;

use Waypoint\Http\{Request, View};
use Waypoint\Attributes\{Controller, Get, Summary, Tags, Param, Query};

#[Controller('/customers')]
class CustomersController
{
    #[Get]
    public function index(Request $request): View
    {
        return new View('HomePage', null, partial: $request->acceptPartial);
    }

    #[Get('/{customerId}/orders')]
    #[Summary('List customer orders')]
    #[Tags(['orders', 'customers'])]
    public function getOrdersByCustomerId(
        #[Param] string $customerId,
        #[Query] ?string $status = null
    ): array {
        return ['customerId' => $customerId, 'status' => $status];
    }
}