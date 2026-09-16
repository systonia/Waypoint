<?php

namespace Waypoint\Options;

use Waypoint\FromArray;

/**
 * Undocumented class
 */
class RendererOptions
{
    use FromArray;

    /**
     * Undocumented variable
     *
     * @var ?string
     */
    public ?string $directory = null {
        get => $this->directory;
        set(?string $value) => $this->directory = $value !== null
            ? (new FileSystemOptions())->buildPath($value)
            : null;
    }

    /**
     * Undocumented variable
     *
     * @var string
     */
    public string $layout = '_Layout'{
        get => $this->layout;
        set(string $value) => $this->layout = $value;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(array $options = [])
    {
        $this->hydrateFromArray($options);

        $this->directory = (new FileSystemOptions())->buildPath($this->directory ?? '');
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        $result = [];

        if (!empty($this->directory)) {
            $result['directory'] = $this->directory;
        }

        if (!empty($this->layout)) {
            $result['layout'] = $this->layout;
        }

        return $result;
    }
}
