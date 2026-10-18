<?php

namespace Waypoint\Tests\Fixtures\Attributes;

use Waypoint\Attributes\{Summary, Tags, Throws, Middleware};
use Waypoint\Exceptions\NotFoundException;

class MetadataMethodFixture
{
    #[Summary('Does the thing')]
    #[Tags(['widgets', 'admin'])]
    #[Throws(
        exception: NotFoundException::class,
        status: 404,
        description: 'Widget missing'
    )]
    #[Middleware(MetadataAuthMiddleware::class)]
    #[Middleware(MetadataLogMiddleware::class)]
    public function handle() {}
}
