<?php

namespace Waypoint\Tests\Fixtures\Attributes;

use Waypoint\Attributes\{Param, Query, Body};

class ParameterBindingFixture
{
    public function handle(
        #[Param] string $customerId,
        #[Param('uuid')] string $userId,
        #[Query] ?string $search,
        #[Query('q')] ?string $term,
        #[Body('payload')] array $body,
        #[Body(of: 'Some\\DTO')] array $items
    ) {
    }
}
