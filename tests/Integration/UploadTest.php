<?php

namespace Waypoint\Tests\Integration;

use Waypoint\Tests\Fixtures\Controllers\UploadController;
use Waypoint\Waypoint;

/** Uploads through the testing kit: withFile() builds what a multipart request would carry. */
final class UploadTest extends IntegrationTestCase
{
    public function testAControllerReadsTheFormsFieldsAndFiles(): void
    {
        Waypoint::create()->attach([UploadController::class]);

        $res = $this->request('POST', '/upload')
            ->withBody(['title' => 'Report'])
            ->withFile('doc', 'a.pdf', 'pdf bytes', 'application/pdf')
            ->withFile('photos[]', 'x.jpg', 'x', 'image/jpeg')
            ->withFile('photos[]', 'y.jpg', 'y', 'image/jpeg')
            ->send();

        $this->assertSame(
            ['title' => 'Report', 'doc' => ['a.pdf', 'application/pdf', 9], 'moved' => 'pdf bytes', 'photos' => 2],
            json_decode($res->body(), true),
        );
    }
}
