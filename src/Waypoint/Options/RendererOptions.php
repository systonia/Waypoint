<?php

namespace Waypoint\Options;

use Waypoint\FromArray;

/** Where view templates live and which layout wraps them. */
class RendererOptions
{
    use FromArray;

    /** Resolved like FileSystemOptions paths; null falls back to the running script's directory. */
    public ?string $directory = null {
        get => $this->directory;
        set(?string $value) => $this->directory = $value !== null ? (new FileSystemOptions())->buildPath($value) : null;
    }

    /** Layout file name including .php ('_Layout.php'). The default has no extension and so matches no file: no layout until configured. */
    public string $layout = '_Layout';

    /** @param array<string, mixed> $options */
    public function __construct(array $options = [])
    {
        $this->hydrateFromArray($options);
        // Through the setter, so a null directory resolves to the script directory like any relative path.
        $this->directory = (new FileSystemOptions())->buildPath($this->directory ?? '');
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        return array_filter(['directory' => $this->directory, 'layout' => $this->layout], fn(?string $v): bool => !empty($v));
    }
}
