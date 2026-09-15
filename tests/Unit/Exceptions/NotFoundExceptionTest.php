<?php

namespace Waypoint\Tests\Unit\Exceptions;

use PHPUnit\Framework\TestCase;
use Waypoint\Exceptions\NotFoundException;
use Psr\Container\NotFoundExceptionInterface;
use Waypoint\Enums\Message;

final class NotFoundExceptionTest extends TestCase
{
    public function testDefaultConstructor(): void
    {
        $e = new NotFoundException();
        $this->assertInstanceOf(NotFoundExceptionInterface::class, $e);
        $this->assertEquals(Message::NotFound->value, $e->getMessage());
        $this->assertEquals(404, $e->getCode());
    }

    public function testCustomMessageAndCode(): void
    {
        $e = new NotFoundException('This is custom', 418);
        $this->assertEquals('This is custom', $e->getMessage());
        $this->assertEquals(418, $e->getCode());
    }

    public function testWithPrevious(): void
    {
        $prev = new \Exception('prev');
        $e = new NotFoundException('fail', 404, $prev);
        $this->assertSame($prev, $e->getPrevious());
    }
}
