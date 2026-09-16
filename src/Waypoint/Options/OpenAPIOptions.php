<?php

namespace Waypoint\Options;

use Waypoint\FromArray;

/** The document-level parts of the generated OpenAPI spec: info, servers, tags, security. */
class OpenAPIOptions
{
    use FromArray;

    public string $title = 'API Documentation';
    public string $version = '1.0.0';
    public string $description = 'Generated API documentation';

    /** @var array<int, array<string, mixed>> */
    public array $servers = [];

    /** @var array<string, string> */
    public array $tags = [];

    /** @var array<string, mixed> */
    public array $securitySchemes = [];

    /** @var array<int, array<string, array<int, string>>> */
    public array $security = [];

    /** @var array{description?: string, url?: string}|null */
    public ?array $externalDocs = null;

    /** @return array<string, mixed> The spec's top-level keys, empty ones omitted. */
    public function toArray(): array
    {
        return array_filter([
            'info' => ['title' => $this->title, 'version' => $this->version, 'description' => $this->description],
            'servers' => $this->servers ?: null,
            'tags' => $this->tags ?: null,
            'components' => $this->securitySchemes ? ['securitySchemes' => $this->securitySchemes] : null,
            'security' => $this->security ?: null,
            'externalDocs' => $this->externalDocs,
        ], fn($v) => $v !== null);
    }
}
