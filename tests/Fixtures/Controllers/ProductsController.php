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
    public function logo(): \SplFileInfo
    {
        // Unlike report(), this hands over a file, read from disk, not written out as text.
        return new \SplFileInfo(__DIR__ . '/../Public/style.css');
    }

    #[Get('/echo.txt')]
    #[FileFormatter(mimetype: 'text/plain', download: true)]
    public function echoText(\Waypoint\Http\Request $req): string
    {
        // A string is always content: even a value that names a file on disk
        $text = $req->query('text');
        return is_string($text) ? $text : '';
    }

    #[Get('/named-download')]
    #[FileFormatter(mimetype: 'text/plain', download: true)]
    public function namedDownload(): \SplFileInfo
    {
        return new \SplFileInfo(__DIR__ . '/../Public/style.css');
    }
}
