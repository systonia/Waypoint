<?php

namespace Waypoint\Tests\Unit\Exceptions;

use PHPUnit\Framework\TestCase;
use Waypoint\Exceptions\ValidationException;
use Waypoint\Enums\Message;

final class ValidationExceptionTest extends TestCase
{
    public function testDefaultConstructor(): void
    {
        $e = new ValidationException();
        $this->assertEquals(Message::ValidationFailed->value, $e->getMessage());
        $this->assertEquals(422, $e->getCode());
        $this->assertEquals([], $e->getErrors());
    }

    public function testCustomMessageAndErrors(): void
    {
        $errors = ['foo' => 'bad', 'bar' => 'missing'];
        $e = new ValidationException('My validation failed', $errors, 409);
        $this->assertEquals('My validation failed', $e->getMessage());
        $this->assertEquals(409, $e->getCode());
        $this->assertEquals($errors, $e->getErrors());
    }

    public function testMessageAsEnum(): void
    {
        $e = new ValidationException(Message::ValidationFailed, [], 422);
        $this->assertEquals(Message::ValidationFailed->value, $e->getMessage());
    }

    public function testWithPrevious(): void
    {
        $prev = new \Exception('fail');
        $e = new ValidationException('test', [], 422, $prev);
        $this->assertSame($prev, $e->getPrevious());
    }
}
