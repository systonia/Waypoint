<?php

namespace Waypoint\Tests\Fixtures\Controllers;

use Waypoint\Attributes\{Controller, Post, Get, Body, FileFormatter, SimpleXmlFormatter};
use Waypoint\Tests\Fixtures\DTO\CreateProductDTO;

#[Controller('/products')]
class ProductsController
{
    #[Post]
    public function create(#[Body] CreateProductDTO $product): array
    {
        return ['name' => $product->name, 'sku' => $product->sku];
    }

    #[Get('/report.txt')]
    #[FileFormatter(mimetype: 'text/plain', filename: 'report.txt', download: true)]
    public function report(): string
    {
        return "sku,name\nA-1,Widget\n";
    }

    #[Get('/export.xml')]
    #[SimpleXmlFormatter]
    public function export(): array
    {
        return ['name' => 'Widget', 'sku' => 'A-1'];
    }

    #[Get('/logo.svg')]
    #[FileFormatter(mimetype: 'image/svg+xml')]
    public function logo(): string
    {
        // Unlike report(), this returns an actual file *path* -- exercises
        // ResultRenderer::renderFileResult()'s read-from-disk branch, as
        // opposed to writing the returned value out directly.
        return __DIR__ . '/../Public/style.css';
    }
}
