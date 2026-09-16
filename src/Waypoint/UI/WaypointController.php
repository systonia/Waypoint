<?php

namespace Waypoint\UI;

use Waypoint\Attributes\{Get, FileFormatter, Controller, Ignore};
use Waypoint\Exceptions\NotFoundException;

/** GET /waypoint.js -- the bundled client. Optional: View::waypointJsTag() renders a <script> tag only when this controller is attached. */
#[Ignore]
#[Controller]
class WaypointController
{
    private string $assetDir;

    /** @param string|null $assetDir Overrides this class's directory as the asset location (tests). */
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

        $content = @file_get_contents($path);
        // @codeCoverageIgnoreStart
        // only a delete/permission race after is_file().
        if ($content === false) {
            throw new NotFoundException("Asset 'waypoint.js' not found.");
        }
        // @codeCoverageIgnoreEnd

        return $content;
    }
}
