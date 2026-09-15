<?php

namespace Waypoint\UI;

use Waypoint\Attributes\{Get, FileFormatter, Controller, Ignore};
use Waypoint\Exceptions\NotFoundException;

/**
 * Serves the bundled waypoint.js client (built separately by the
 * Waypoint-UI project, shipped inside this package alongside this class)
 * at GET /waypoint.js -- a real attribute-routed controller, the same as
 * Waypoint\OpenAPI\OpenAPIController owns swagger.html/spec.json. Like
 * OpenAPIController, it's optional: nothing serves this route unless the
 * app explicitly attaches it, e.g. `$app->attach([WaypointController::class])`.
 * View::waypointJsTag() reflects that -- it renders a <script> tag only
 * when this controller is actually attached (see Router::renderView()),
 * empty string otherwise.
 */
#[Ignore]
#[Controller]
class WaypointController
{
    private string $assetDir;

    /**
     * @param string|null $assetDir Overrides where waypoint.js is read
     *  from; defaults to this class's own directory. Exists mainly so
     *  tests can point at a directory that deliberately doesn't have the
     *  file, without touching the real bundled asset.
     */
    public function __construct(?string $assetDir = null)
    {
        $this->assetDir = $assetDir ?? __DIR__;
    }

    #[Get('waypoint.js')]
    #[FileFormatter(filename: 'waypoint.js', mimetype: 'application/javascript; charset=utf-8', download: false)]
    public function serve(): string
    {
        $path = "{$this->assetDir}/waypoint.js";

        if (!is_file($path) || !is_readable($path)) {
            throw new NotFoundException("Asset 'waypoint.js' not found.");
        }

        return file_get_contents($path);
    }
}
