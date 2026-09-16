<?php

namespace Waypoint\Options;

use Waypoint\FromArray;

/**
 * Undocumented class
 */
class OpenAPIOptions
{
    use FromArray;

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
            // A single-level assignment (not $result['components']['securitySchemes'] = ...)
            // on purpose: PHPStan can't track a doubly-nested offset being
            // auto-vivified through an array<string, mixed>-typed $result.
            $result['components'] = ['securitySchemes' => $this->securitySchemes];
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
