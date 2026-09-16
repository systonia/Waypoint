<?php

namespace Waypoint\Tests\Unit\Http;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Waypoint\Http\Redirect;

final class RedirectTest extends TestCase
{
    public function testDefaultsTo302(): void
    {
        $redirect = new Redirect('/dashboard');

        $this->assertSame('/dashboard', $redirect->location);
        $this->assertSame(302, $redirect->status);
    }

    public function testAcceptsAnyThreeHundredStatus(): void
    {
        $this->assertSame(301, (new Redirect('/x', 301))->status);
        $this->assertSame(303, (new Redirect('/x', 303))->status);
        $this->assertSame(307, (new Redirect('/x', 307))->status);
        $this->assertSame(308, (new Redirect('/x', 308))->status);
    }

    public function testRejectsANonRedirectStatus(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('3xx');

        new Redirect('/x', 200);
    }

    public function testRejectsAnEmptyLocation(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Redirect('');
    }

    public function testRejectsALocationWithALineBreak(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('line breaks');

        new Redirect("/x\r\nSet-Cookie: evil=1");
    }
}
