<?php

namespace Waypoint\OpenAPI;

use Waypoint\Waypoint;
use RuntimeException;
use Waypoint\Attributes\{Get, FileFormatter, Inject, Controller, Ignore, Param};
use Waypoint\OpenAPI\OpenAPIGenerator;
use Waypoint\Router;
use Waypoint\Exceptions\NotFoundException;

/** GET /openapi/swagger.html, /openapi/spec.json and /openapi/spec.{version}.json. Optional: attach it to serve them. */
#[Ignore]
#[Controller('/openapi')]
class OpenAPIController
{
    private string $assetDir;

    /** @param string|null $assetDir Overrides this class's directory as the asset location (tests). */
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
     * @return array<string, mixed>
     */
    #[Get('spec.json')]
    #[FileFormatter(filename: 'spec.json', mimetype: 'application/json', download: false)]
    public function spec(): array
    {
        return (new OpenAPIGenerator(Waypoint::getRouter()))->generate();
    }

    /**
     * One spec per #[Version] in use; 404 for a version nothing was compiled with (the set is only known after attach()).
     * @return array<string, mixed>
     */
    #[Get('spec.{version}.json')]
    #[FileFormatter(filename: 'spec.json', mimetype: 'application/json', download: false)]
    public function versionedSpec(#[Param] string $version): array
    {
        $generator = new OpenAPIGenerator(Waypoint::getRouter());

        if (!$generator->hasVersion($version)) {
            throw new NotFoundException("OpenAPI spec for version '$version' not found.");
        }

        return $generator->generate($version);
    }

    private function renderBundledFile(string $filename): string
    {
        $safeName = basename($filename);
        $path = "{$this->assetDir}/$safeName";

        if (!is_file($path) || !is_readable($path)) {
            throw new NotFoundException("Asset '$safeName' not found.");
        }

        $content = @file_get_contents($path);
        // @codeCoverageIgnoreStart
        // only a delete/permission race after is_file().
        if ($content === false) {
            throw new NotFoundException("Asset '$safeName' not found.");
        }
        // @codeCoverageIgnoreEnd

        return $content;
    }
}
