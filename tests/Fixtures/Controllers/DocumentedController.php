<?php

namespace Waypoint\Tests\Fixtures\Controllers;

use Waypoint\Attributes\{Controller, Get, Post, Put, Ignore, Summary, Tags, Throws, Param, Query, Body};
use Waypoint\Exceptions\NotFoundException;
use Waypoint\Tests\Fixtures\DTO\{CreateProductDTO, CustomerDTO, CyclicNodeDTO, LegacyDTO, MiscTypesDTO};
use Waypoint\Tests\Fixtures\Services\ExampleService;

#[Controller('/documented')]
class DocumentedController
{
    #[Get('/{id}')]
    #[Summary('Fetch a widget')]
    #[Tags(['widgets'])]
    #[Throws(exception: NotFoundException::class, status: 404, description: 'Widget missing')]
    public function show(#[Param] string $id): array
    {
        return ['id' => $id];
    }

    #[Put('/{id}')]
    #[Summary(text: 'Replace a widget')]
    #[Tags(tags: ['widgets-named-arg'])]
    #[Throws(exception: NotFoundException::class, status: 404, description: 'Widget missing')]
    public function replace(#[Param] string $id, #[Body] CreateProductDTO $dto): array
    {
        return ['id' => $id];
    }

    #[Get('/metrics')]
    public function metrics(#[Query] float $threshold, #[Query] bool $active): array
    {
        return ['threshold' => $threshold, 'active' => $active];
    }

    #[Get('/weird-params')]
    public function weirdParams(
        int $page, // unattributed scalar -- Router's implicit "Scalar" binding
        #[Query] $untyped,
        #[Query] array $filters,
        #[Query] ExampleService $notReallyBindable
    ): array {
        return ['page' => $page];
    }

    #[Post('/raw-body')]
    public function rawBody(#[Body] string $raw): array
    {
        return ['ok' => true];
    }

    #[Post('/misc-types')]
    public function miscTypes(#[Body] MiscTypesDTO $dto): array
    {
        return ['ok' => true];
    }

    #[Post]
    public function create(#[Body] CreateProductDTO $dto): array
    {
        return ['ok' => true];
    }

    #[Post('/bulk-products')]
    public function bulkCreate(#[Body(of: CreateProductDTO::class)] array $products): array
    {
        return ['count' => count($products), 'skus' => array_map(fn($p) => $p->sku, $products)];
    }

    #[Post('/bulk-ghosts')]
    public function bulkGhosts(#[Body(of: 'Waypoint\Tests\Fixtures\DTO\DoesNotExistDTO')] array $items): array
    {
        return ['ok' => true];
    }

    #[Get('/search')]
    public function search(#[Query] string $q, #[Query] ?int $limit = null): array
    {
        return ['q' => $q, 'limit' => $limit];
    }

    #[Post('/customers')]
    public function createCustomer(#[Body] CustomerDTO $dto): array
    {
        return ['ok' => true];
    }

    #[Post('/cyclic')]
    public function cyclic(#[Body] CyclicNodeDTO $dto): array
    {
        return ['ok' => true];
    }

    #[Post('/legacy-body')]
    public function legacyBody(#[Body] LegacyDTO $dto): array
    {
        return ['ok' => true];
    }

    #[Get('/legacy')]
    #[\Deprecated]
    public function legacy(): array
    {
        return [];
    }

    #[Get('/hidden')]
    #[Ignore]
    public function hidden(): array
    {
        return [];
    }
}
