<?php

namespace Waypoint\Tests\Unit\Options;

use PHPUnit\Framework\TestCase;
use Waypoint\Options\OpenAPIOptions;

final class OpenAPIOptionsTest extends TestCase
{
    public function testDefaults(): void
    {
        $opts = new OpenAPIOptions();
        $this->assertSame('API Documentation', $opts->title);
        $this->assertSame('1.0.0', $opts->version);
        $this->assertSame('Generated API documentation', $opts->description);
        $this->assertSame([], $opts->servers);
        $this->assertSame([], $opts->tags);
        $this->assertNull($opts->externalDocs);
    }

    public function testConstructorIgnoresUnknownKeysWithoutError(): void
    {
        $opts = new OpenAPIOptions(['title' => 'My API', 'notAProperty' => 'ignored']);
        $this->assertSame('My API', $opts->title);
        $this->assertFalse(isset($opts->notAProperty));
    }

    public function testToArrayAlwaysIncludesInfo(): void
    {
        $arr = (new OpenAPIOptions())->toArray();
        $this->assertSame([
            'title' => 'API Documentation',
            'version' => '1.0.0',
            'description' => 'Generated API documentation',
        ], $arr['info']);
    }

    public function testToArrayOmitsEmptyOptionalSections(): void
    {
        $arr = (new OpenAPIOptions())->toArray();
        $this->assertArrayNotHasKey('servers', $arr);
        $this->assertArrayNotHasKey('tags', $arr);
        $this->assertArrayNotHasKey('components', $arr);
        $this->assertArrayNotHasKey('security', $arr);
        $this->assertArrayNotHasKey('externalDocs', $arr);
    }

    public function testToArrayIncludesConfiguredOptionalSections(): void
    {
        $opts = new OpenAPIOptions([
            'servers' => [['url' => 'https://api.example.com']],
            'tags' => [['name' => 'orders']],
            'securitySchemes' => ['bearerAuth' => ['type' => 'http', 'scheme' => 'bearer']],
            'security' => [['bearerAuth' => []]],
            'externalDocs' => ['url' => 'https://docs.example.com'],
        ]);
        $arr = $opts->toArray();

        $this->assertSame([['url' => 'https://api.example.com']], $arr['servers']);
        $this->assertSame([['name' => 'orders']], $arr['tags']);
        $this->assertSame(['bearerAuth' => ['type' => 'http', 'scheme' => 'bearer']], $arr['components']['securitySchemes']);
        $this->assertSame([['bearerAuth' => []]], $arr['security']);
        $this->assertSame(['url' => 'https://docs.example.com'], $arr['externalDocs']);
    }
}
