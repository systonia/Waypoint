<?php

namespace Waypoint\Tests\Unit\Http;

use PHPUnit\Framework\TestCase;
use Waypoint\Http\Response;

final class ResponseTest extends TestCase
{
    protected function setUp(): void
    {
        // PHP tracks "sent" headers process-wide even under the CLI SAPI;
        // clear them so each test observes only its own send() call.
        header_remove();
    }

    private function sentHeaderLines(): array
    {
        // Plain headers_list() is always empty under the CLI SAPI (there is
        // no real HTTP response to attach headers to); Xdebug separately
        // tracks every header() call regardless of SAPI, so prefer that.
        return function_exists('xdebug_get_headers') ? xdebug_get_headers() : headers_list();
    }

    private function sentHeaderNames(): array
    {
        return array_map(
            fn(string $h) => strtolower(explode(':', $h, 2)[0]),
            $this->sentHeaderLines()
        );
    }

    /** Every "Set-Cookie" header's value (the part after "Set-Cookie: "), in send() order. */
    private function sentCookies(): array
    {
        return array_values(array_filter(array_map(
            function (string $h) {
                [$name, $value] = array_map('trim', explode(':', $h, 2) + [1 => '']);
                return strtolower($name) === 'set-cookie' ? $value : null;
            },
            $this->sentHeaderLines()
        )));
    }

    public function testMethodsAreChainable(): void
    {
        $res = new Response();
        $this->assertSame($res, $res->status(201));
        $this->assertSame($res, $res->withHeader('X-Test', '1'));
        $this->assertSame($res, $res->write('body'));
    }

    public function testWriteAppendsAcrossCalls(): void
    {
        $res = (new Response())->write('Hello, ')->write('World!');

        ob_start();
        $res->send();
        $output = ob_get_clean();

        $this->assertSame('Hello, World!', $output);
    }

    public function testSendSetsConfiguredStatusCode(): void
    {
        $res = (new Response())->status(201);

        ob_start();
        $res->send();
        ob_end_clean();

        $this->assertSame(201, http_response_code());
    }

    public function testSendDefaultsStatusTo200(): void
    {
        $res = new Response();

        ob_start();
        $res->send();
        ob_end_clean();

        $this->assertSame(200, http_response_code());
    }

    public function testSendAutoAddsContentLengthWhenNotProvided(): void
    {
        $res = (new Response())->write('12345');

        ob_start();
        $res->send();
        ob_end_clean();

        $this->assertContains('content-length', $this->sentHeaderNames());
    }

    public function testSendIncludesExplicitlySetHeaders(): void
    {
        $res = (new Response())->withHeader('X-Custom', 'yes');

        ob_start();
        $res->send();
        ob_end_clean();

        $this->assertContains('x-custom', $this->sentHeaderNames());
    }

    public function testWithCookieIsChainable(): void
    {
        $res = new Response();
        $this->assertSame($res, $res->withCookie('session', 'abc'));
    }

    public function testWithCookieDefaultsToHttpOnlyAndSameSiteLaxWithNoMaxAge(): void
    {
        $res = (new Response())->withCookie('session', 'abc123');

        ob_start();
        $res->send();
        ob_end_clean();

        $cookies = $this->sentCookies();
        $this->assertCount(1, $cookies);
        $this->assertSame('session=abc123; Path=/; HttpOnly; SameSite=Lax', $cookies[0]);
    }

    public function testWithCookieIncludesMaxAgeWhenGiven(): void
    {
        $res = (new Response())->withCookie('session', 'abc123', maxAge: 3600);

        ob_start();
        $res->send();
        ob_end_clean();

        $this->assertSame('session=abc123; Path=/; Max-Age=3600; HttpOnly; SameSite=Lax', $this->sentCookies()[0]);
    }

    public function testWithCookieHonorsAllExplicitAttributes(): void
    {
        $res = (new Response())->withCookie(
            'session',
            'abc123',
            maxAge: 60,
            path: '/app',
            domain: 'example.com',
            secure: true,
            httpOnly: false,
            sameSite: 'Strict',
        );

        ob_start();
        $res->send();
        ob_end_clean();

        $this->assertSame(
            'session=abc123; Path=/app; Domain=example.com; Max-Age=60; Secure; SameSite=Strict',
            $this->sentCookies()[0]
        );
    }

    public function testWithCookieOmitsSameSiteWhenExplicitlyNull(): void
    {
        $res = (new Response())->withCookie('session', 'abc123', sameSite: null);

        ob_start();
        $res->send();
        ob_end_clean();

        $this->assertSame('session=abc123; Path=/; HttpOnly', $this->sentCookies()[0]);
    }

    public function testWithCookieUrlEncodesNameAndValue(): void
    {
        $res = (new Response())->withCookie('a name', 'a value');

        ob_start();
        $res->send();
        ob_end_clean();

        $this->assertStringStartsWith(rawurlencode('a name') . '=' . rawurlencode('a value'), $this->sentCookies()[0]);
    }

    public function testMultipleWithCookieCallsProduceSeparateSetCookieHeaders(): void
    {
        $res = (new Response())
            ->withCookie('a', '1')
            ->withCookie('b', '2');

        ob_start();
        $res->send();
        ob_end_clean();

        $cookies = $this->sentCookies();
        $this->assertCount(2, $cookies);
        $this->assertStringStartsWith('a=1;', $cookies[0]);
        $this->assertStringStartsWith('b=2;', $cookies[1]);
    }

    public function testWithoutCookieIsChainable(): void
    {
        $res = new Response();
        $this->assertSame($res, $res->withoutCookie('session'));
    }

    public function testWithoutCookieClearsWithAnEmptyValueAndMaxAgeZero(): void
    {
        $res = (new Response())->withoutCookie('session');

        ob_start();
        $res->send();
        ob_end_clean();

        $this->assertSame('session=; Path=/; Max-Age=0; HttpOnly; SameSite=Lax', $this->sentCookies()[0]);
    }

    public function testWithoutCookieHonorsPathAndDomain(): void
    {
        $res = (new Response())->withoutCookie('session', path: '/app', domain: 'example.com');

        ob_start();
        $res->send();
        ob_end_clean();

        $this->assertSame('session=; Path=/app; Domain=example.com; Max-Age=0; HttpOnly; SameSite=Lax', $this->sentCookies()[0]);
    }
}
