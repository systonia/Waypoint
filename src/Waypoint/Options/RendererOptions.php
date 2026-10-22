<?php

namespace Waypoint\Options;

/** Where view templates live and which layout wraps them. */
class RendererOptions
{
    /** Resolved like FileSystemOptions paths; null falls back to the running script's directory. */
    public ?string $directory = null {
        get => $this->directory;
        set(?string $value) => $this->directory = $value !== null ? (new FileSystemOptions())->buildPath($value) : null;
    }

    /** Layout file name including .php ('_Layout.php'). The default has no extension and so matches no file: no layout until configured. */
    public string $layout = '_Layout';

    public function __construct()
    {
        // Through the setter, so the directory starts as the script's directory, like any relative path.
        $this->directory = (new FileSystemOptions())->buildPath('');
    }
}
