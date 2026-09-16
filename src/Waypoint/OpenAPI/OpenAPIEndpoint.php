<?php

namespace Waypoint\OpenAPI;

use Waypoint\Container;
use Waypoint\Exceptions\NotFoundException;
use Waypoint\Http\{Request, Response};
use Waypoint\Options\OpenAPIOptions;
use Waypoint\Plugin\Endpoint;
use Waypoint\Router;

/**
 * Serves the OpenAPI document and Swagger UI under OpenAPIOptions::$path
 * once OpenAPIOptions::$enabled is set -- no controller to attach:
 *
 *   GET {path}/spec.json           the combined document
 *   GET {path}/spec.{version}.json one #[Version]'s document (404 for an unknown version)
 *   GET {path}/swagger.html        bundled Swagger UI
 *
 * The first Plugin\Endpoint: Router consults it before route matching.
 */
final class OpenAPIEndpoint implements Endpoint
{
    /** @param string|null $assetDir Overrides this class's directory as the location of swagger.html (tests). */
    public function __construct(private Router $router, private Container $container, private ?string $assetDir = null)
    {
        $this->assetDir ??= __DIR__;
    }

    public function serve(string $method, string $path, Request $req, Response $res): bool
    {
        $options = $this->container->get(OpenAPIOptions::class);
        $prefix = $options->path . '/';
        if ($method !== 'GET' || !$options->enabled || !str_starts_with($path, $prefix)) {
            return false;
        }
        $router = $this->router;

        $file = substr($path, strlen($prefix));
        if ($file === 'swagger.html') {
            $res->withHeader('Content-Type', 'text/html')->write($this->swaggerHtml());
            return true;
        }
        if ($file === 'spec.json') {
            $this->writeJson($res, (new OpenAPIGenerator($router))->generate());
            return true;
        }
        if (preg_match('/^spec\.([^\/.]+)\.json$/', $file, $m) === 1) {
            $generator = new OpenAPIGenerator($router);
            if (!$generator->hasVersion($m[1])) {
                throw new NotFoundException("OpenAPI spec for version '{$m[1]}' not found.");
            }
            $this->writeJson($res, $generator->generate($m[1]));
            return true;
        }
        return false;
    }

    public function swaggerHtml(): string
    {
        $file = "{$this->assetDir}/swagger.html";
        $content = is_file($file) ? @file_get_contents($file) : false;
        if ($content === false) {
            throw new NotFoundException("Asset 'swagger.html' not found.");
        }
        return $content;
    }

    /** @param array<string, mixed> $spec */
    private function writeJson(Response $res, array $spec): void
    {
        $res->withHeader('Content-Type', 'application/json')->write(json_encode($spec) ?: 'null');
    }
}
