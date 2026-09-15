<?php

namespace Waypoint\Tests\Unit\OpenAPI;

use PHPUnit\Framework\TestCase;
use Waypoint\Exceptions\NotFoundException;
use Waypoint\OpenAPI\OpenAPIController;

final class OpenAPIControllerTest extends TestCase
{
    public function testSwaggerReadsTheRealBundledFileByDefault(): void
    {
        $html = (new OpenAPIController())->swagger();
        $this->assertStringContainsString('<html', $html);
    }

    public function testSwaggerThrowsNotFoundWhenTheAssetIsMissing(): void
    {
        // swagger.html are only guaranteed to exist in the
        // real asset dir; an empty dir lets us test the "missing" branch
        // without touching those actual bundled files.
        $controller = new OpenAPIController(sys_get_temp_dir() . '/missing-assets-' . uniqid());

        $this->expectException(NotFoundException::class);
        $controller->swagger();
    }
}
