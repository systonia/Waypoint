<?php

namespace Waypoint\Tests\Unit\Exceptions;

use Exception;
use PHPUnit\Framework\TestCase;
use Waypoint\Exceptions\HttpException;

final class HttpExceptionTest extends TestCase
{
    public function testDefaultsExposeStatusCodeTitleAndAboutBlankType(): void
    {
        $e = new HttpException(403, 'Forbidden');

        $this->assertSame(403, $e->getStatusCode());
        $this->assertSame(403, $e->getCode());
        $this->assertSame('Forbidden', $e->getTitle());
        $this->assertSame('about:blank', $e->getType());
        $this->assertNull($e->getDetail());
        $this->assertNull($e->getInstance());
        $this->assertSame('Forbidden', $e->getMessage());
    }

    public function testDetailBecomesTheExceptionMessageWhenGiven(): void
    {
        $e = new HttpException(404, 'Not Found', 'Widget 42 not found');

        $this->assertSame('Widget 42 not found', $e->getDetail());
        $this->assertSame('Widget 42 not found', $e->getMessage());
    }

    public function testCustomTypeAndInstanceAreExposed(): void
    {
        $e = new HttpException(
            statusCode: 409,
            title: 'Conflict',
            detail: 'Widget 42 already exists',
            type: 'https://example.com/problems/conflict',
            instance: '/widgets/42'
        );

        $this->assertSame('https://example.com/problems/conflict', $e->getType());
        $this->assertSame('/widgets/42', $e->getInstance());
    }

    public function testWithPrevious(): void
    {
        $prev = new Exception('root cause');
        $e = new HttpException(500, 'Internal Server Error', previous: $prev);

        $this->assertSame($prev, $e->getPrevious());
    }

    public function testToProblemDetailsIncludesOnlyTheMembersThatAreSet(): void
    {
        $e = new HttpException(403, 'Forbidden');

        $this->assertSame(
            ['type' => 'about:blank', 'title' => 'Forbidden', 'status' => 403],
            $e->toProblemDetails()
        );
    }

    public function testToProblemDetailsIncludesDetailAndInstanceWhenSet(): void
    {
        $e = new HttpException(
            statusCode: 409,
            title: 'Conflict',
            detail: 'Widget 42 already exists',
            type: 'https://example.com/problems/conflict',
            instance: '/widgets/42'
        );

        $this->assertSame(
            [
                'type' => 'https://example.com/problems/conflict',
                'title' => 'Conflict',
                'status' => 409,
                'detail' => 'Widget 42 already exists',
                'instance' => '/widgets/42',
            ],
            $e->toProblemDetails()
        );
    }
}
