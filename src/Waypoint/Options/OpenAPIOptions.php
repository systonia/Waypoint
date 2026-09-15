<?php

namespace Waypoint\Options;

/**
 * Undocumented class
 */
class OpenAPIOptions
{
    /**
     * Undocumented variable
     *
     * @var string
     */
    public string $title = 'API Documentation';

    /**
     * Undocumented variable
     *
     * @var string
     */
    public string $version = '1.0.0';

    /**
     * Undocumented variable
     *
     * @var string
     */
    public string $description = 'Generated API documentation';

    // Optional extras for future extension
    /**
     * @var array<int, array<string, mixed>>
     */
    public array $servers = [];

    /**
     * Undocumented variable
     *
     * @var array<string, string>
     */
    public array $tags = [];

    /**
     * Undocumented variable
     *
     * @var array<string, mixed>
     */
    public array $securitySchemes = [];

    /**
     * Undocumented variable
     *
     * @var array<int, array<string, array<int, string>>>
     */
    public array $security = [];

    /**
     * Undocumented variable
     *
     * @var array{description?: string, url?: string}|null
     */
    public ?array $externalDocs = null;

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(array $options = [])
    {
        foreach ($options as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $info = [
            'title' => $this->title,
            'version' => $this->version,
            'description' => $this->description,
        ];

        $result = [
            'info' => $info,
        ];

        if (!empty($this->servers)) {
            $result['servers'] = $this->servers;
        }

        if (!empty($this->tags)) {
            $result['tags'] = $this->tags;
        }

        if (!empty($this->securitySchemes)) {
            $result['components']['securitySchemes'] = $this->securitySchemes;
        }

        if (!empty($this->security)) {
            $result['security'] = $this->security;
        }

        if ($this->externalDocs !== null) {
            $result['externalDocs'] = $this->externalDocs;
        }

        return $result;
    }
}
