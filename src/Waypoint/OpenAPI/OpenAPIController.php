<?php

namespace Waypoint\OpenAPI;

use Waypoint\Waypoint;
use RuntimeException;
use Waypoint\Attributes\{Get, FileFormatter, Inject, Controller, Ignore, Param};
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
     * @param string|null $assetDir Overrides where swagger.html
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
     * @return array<string, mixed>
     */
    #[Get('spec.json')]
    #[FileFormatter(filename: 'spec.json', mimetype: 'application/json', download: false)]
    public function spec(): array
    {
        return (new OpenAPIGenerator(Waypoint::getRouter()))->generate();
    }

    /**
     * GET /openapi/spec.v1.json, spec.v2.json, ... -- one per distinct
     * #[Version] actually used by an attached route. A dynamic route
     * rather than one static #[Get] per version: the set of versions that
     * exist is only known once controllers are attached/compiled, not at
     * class-definition time when attributes are declared. 404s (rather
     * than an empty spec) for a version nothing was ever compiled with.
     *
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
        // Only reachable via a race (deleted/permissions changed between
        // is_file()/is_readable() and file_get_contents()) that can't be
        // reliably reproduced cross platform -- same guard as
        // FileSystem::readViewAssetFile().
        if ($content === false) {
            throw new NotFoundException("Asset '$safeName' not found.");
        }
        // @codeCoverageIgnoreEnd

        return $content;
    }
}
