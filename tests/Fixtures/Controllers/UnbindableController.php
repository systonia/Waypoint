<?php

namespace Waypoint\Tests\Fixtures\Controllers;

#[\Waypoint\Attributes\Controller('/unbindable')]
final class UnbindableController
{
    #[\Waypoint\Attributes\Get('')]
    public function show(\DateTimeImmutable $when): array
    {
        return [];
    }
}
