<?php

namespace Waypoint\Tests\Fixtures\Controllers\Admin;

use Waypoint\Attributes\{Body, Controller, Get, Post, Throws};
use Waypoint\Exceptions\{ForbiddenException, NotFoundException};
use Waypoint\Tests\Fixtures\DTO\Billing\LineItem as BillingItem;
use Waypoint\Tests\Fixtures\DTO\Shipping\LineItem as ShippingItem;

/** Shares its short name and a method name with Fixtures\Controllers\OrdersController; two DTOs share theirs too */
#[Controller('/admin/orders')]
class OrdersController
{
    #[Get('/{orderId}')]
    #[Throws(NotFoundException::class)]
    #[Throws(
        exception: ForbiddenException::class,
        status: 403
    )]
    #[Throws(\RuntimeException::class)]
    public function ping(): ?string
    {
        return null;
    }

    #[Post('/billing')]
    public function billing(#[Body] BillingItem $item): array
    {
        return [];
    }

    #[Post('/shipping')]
    public function shipping(#[Body] ShippingItem $item): array
    {
        return [];
    }
}
