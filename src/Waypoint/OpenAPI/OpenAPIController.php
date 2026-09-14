<?php

namespace Waypoint\OpenAPI;

use Waypoint\Waypoint;
use RuntimeException;
use Waypoint\Attributes\{Get, FileFormatter, Inject, Controller, Ignore};
use Waypoint\OpenAPI\OpenAPIGenerator;
use Waypoint\Router;
use Waypoint\Exceptions\NotFoundException;

/**
 *
 */
#[Ignore]
#[Controller('/openapi')]
class OpenAPIController
{
    private string $assetDir;

    /**
     * @param string|null $assetDir Overrides where swagger.html/lentodoc.html
     *  are read from; defaults to this class's own directory. Exists mainly
     *  so tests can point at a directory that deliberately doesn't have
     *  these files, without touching the real bundled assets.
     */
    public function __construct(?string $assetDir = null)
    {
        $this->assetDir = $assetDir ?? __DIR__;
    }

    #[Get('swagger.html')]
    #[FileFormatter(filename: 'swagger.html', mimetype: 'text/html', download: false)]
    public function swagger(): string
    {
        return $this->renderBundledFile('swagger.html');
    }

    /**
     * @return array
     */
    #[Get('spec.json')]
    #[FileFormatter(filename: 'spec.json', mimetype: 'application/json', download: false)]
    public function spec(): array
    {
        return (new OpenAPIGenerator(Waypoint::getRouter()))->generate();
    }

    private function renderBundledFile(string $filename): string
    {
        $safeName = basename($filename);
        $path = "{$this->assetDir}/$safeName";

        if (!is_file($path) || !is_readable($path)) {
            throw new NotFoundException("Asset '$safeName' not found.");
        }

        return file_get_contents($path);
    }
}
