<?php

namespace Waypoint\Options;

/**
 * Undocumented class
 */
class RendererOptions
{
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
        set(?string $value) => $this->layout = $value;
    }

    /**
     * Undocumented function
     *
     * @param array $options
     */
    public function __construct(array $options = [])
    {
        foreach ($options as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }

        $this->directory = (new FileSystemOptions())->buildPath($this->directory ?? '');
    }

    /**
     * Undocumented function
     *
     * @return array
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
