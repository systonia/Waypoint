<?php

namespace Waypoint\Tests\Unit\OpenAPI;

use PHPUnit\Framework\TestCase;
use Waypoint\Container;
use Waypoint\Exceptions\NotFoundException;
use Waypoint\OpenAPI\OpenAPIEndpoint;
use Waypoint\Router;

final class OpenAPIEndpointTest extends TestCase
{
    public function testSwaggerReadsTheRealBundledFileByDefault(): void
    {
        $this->assertStringContainsString('<html', $this->endpoint()->swaggerHtml());
    }

    public function testSwaggerThrowsNotFoundWhenTheAssetIsMissing(): void
    {
        $endpoint = $this->endpoint(sys_get_temp_dir() . '/missing-assets-' . uniqid());

        $this->expectException(NotFoundException::class);
        $endpoint->swaggerHtml();
    }

    private function endpoint(?string $assetDir = null): OpenAPIEndpoint
    {
        return new OpenAPIEndpoint($this->createStub(Router::class), new Container(), $assetDir);
    }
}
