<?php

namespace Waypoint\Tests\Unit\Enums;

use PHPUnit\Framework\TestCase;
use Waypoint\Enums\Message;

final class MessageEnumTest extends TestCase
{
    public function testFormatReplacesVariables(): void
    {
        $msg = Message::GeneratorPropertyDoesNotExist;
        $vars = [
            'property' => 'type',
            'name' => 'foo',
            'rc' => 'BarClass',
        ];
        $expected = 'OpenAPIGenerator: Property type "type" does not exist (property "foo" in class "BarClass")';
        $this->assertEquals($expected, $msg->format($vars));
    }

    public function testFormatLeavesMissingVariablesUnreplaced(): void
    {
        $msg = Message::GeneratorPropertyDoesNotExist;
        $vars = [
            'property' => 'id',
            // 'name' intentionally missing
            'rc' => 'TestClass',
        ];
        $expected = 'OpenAPIGenerator: Property type "id" does not exist (property "{name}" in class "TestClass")';
        $this->assertEquals($expected, $msg->format($vars));
    }

    public function testFormatWithNoVars(): void
    {
        $this->assertEquals('Forbidden', Message::Forbidden->format());
    }

    public function testInterpolateAcceptsNamedParams(): void
    {
        $result = Message::GeneratorClassDoesNotExist->interpolate(fqcn: 'Foo\\Bar');
        $this->assertEquals(
            'OpenAPIGenerator: generateModelSchema - class "Foo\\Bar" does not exist.',
            $result
        );
    }

    public function testInterpolateIgnoresExtraParams(): void
    {
        $result = Message::GeneratorClassDoesNotExist->interpolate(fqcn: 'ClassA', extra: 'ignored');
        $this->assertEquals(
            'OpenAPIGenerator: generateModelSchema - class "ClassA" does not exist.',
            $result
        );
    }

    public function testEnumValue(): void
    {
        $this->assertEquals('Not Found', Message::NotFound->value);
    }
}
