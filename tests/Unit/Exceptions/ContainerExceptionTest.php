<?php

namespace Waypoint\Tests\Unit\Exceptions;

use PHPUnit\Framework\TestCase;
use Waypoint\Exceptions\ContainerException;
use Psr\Container\ContainerExceptionInterface;

final class ContainerExceptionTest extends TestCase
{
    public function testInstantiate(): void
    {
        $e = new ContainerException("fail", 123);
        $this->assertInstanceOf(ContainerException::class, $e);
        $this->assertInstanceOf(ContainerExceptionInterface::class, $e);
        $this->assertEquals("fail", $e->getMessage());
        $this->assertEquals(123, $e->getCode());
    }

    public function testWithPrevious(): void
    {
        $prev = new \Exception('root cause');
        $e = new ContainerException('wrapped', 0, $prev);
        $this->assertSame($prev, $e->getPrevious());
    }
}
