<?php

namespace Waypoint\Tests\Unit\UI;

use PHPUnit\Framework\TestCase;
use Waypoint\Exceptions\NotFoundException;
use Waypoint\UI\WaypointController;

final class WaypointControllerTest extends TestCase
{
    public function testServeReadsTheRealBundledFileByDefault(): void
    {
        $content = (new WaypointController())->serve();

        $this->assertNotSame('', $content);
        $this->assertSame(
            file_get_contents(dirname((new \ReflectionClass(WaypointController::class))->getFileName()) . '/waypoint.js'),
            $content
        );
    }

    public function testServeThrowsNotFoundWhenTheAssetIsMissing(): void
    {
        // Mirrors OpenAPIControllerTest's equivalent: an empty dir lets us
        // test the "missing" branch without touching the real bundled file.
        $controller = new WaypointController(sys_get_temp_dir() . '/waypoint-missing-assets-' . uniqid());

        $this->expectException(NotFoundException::class);
        $controller->serve();
    }
}
