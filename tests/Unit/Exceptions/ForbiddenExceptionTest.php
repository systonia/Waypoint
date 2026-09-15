<?php

namespace Waypoint\Tests\Unit\Exceptions;

use PHPUnit\Framework\TestCase;
use Waypoint\Exceptions\ForbiddenException;
use Waypoint\Enums\Message;

final class ForbiddenExceptionTest extends TestCase
{
    public function testDefaultConstructor(): void
    {
        $e = new ForbiddenException();
        $this->assertEquals(Message::Forbidden->value, $e->getMessage());
        $this->assertEquals(403, $e->getCode());
    }

    public function testCustomMessageAndCode(): void
    {
        $e = new ForbiddenException('Custom forbidden', 402);
        $this->assertEquals('Custom forbidden', $e->getMessage());
        $this->assertEquals(402, $e->getCode());
    }

    public function testWithPrevious(): void
    {
        $prev = new \Exception('Prev message');
        $e = new ForbiddenException('fail', 403, $prev);
        $this->assertSame($prev, $e->getPrevious());
    }
}
