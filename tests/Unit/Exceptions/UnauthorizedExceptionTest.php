<?php

namespace Waypoint\Tests\Unit\Exceptions;

use PHPUnit\Framework\TestCase;
use Waypoint\Exceptions\UnauthorizedException;
use Waypoint\Enums\Message;

final class UnauthorizedExceptionTest extends TestCase
{
    public function testDefaultConstructor(): void
    {
        $e = new UnauthorizedException();
        $this->assertEquals(Message::Unauthorized->value, $e->getMessage());
        $this->assertEquals(401, $e->getCode());
    }

    public function testCustomMessageAndCode(): void
    {
        $e = new UnauthorizedException('Custom unauthorized', 499);
        $this->assertEquals('Custom unauthorized', $e->getMessage());
        $this->assertEquals(499, $e->getCode());
    }

    public function testWithPrevious(): void
    {
        $prev = new \Exception('previous');
        $e = new UnauthorizedException('fail', 401, $prev);
        $this->assertSame($prev, $e->getPrevious());
    }
}
