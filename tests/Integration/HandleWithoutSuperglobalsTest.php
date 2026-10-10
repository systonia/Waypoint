<?php

namespace Waypoint\Tests\Integration;

use Waypoint\Http\Request;
use Waypoint\Waypoint;
use Waypoint\Tests\Fixtures\Controllers\{CustomersController, GzipMethodController, RedirectsController};

/**
 * App::handle() for a runtime that hands over requests itself: everything comes from the Request,
 * the answer comes back as a Response, and nothing is read from or written to the SAPI.
 */
final class HandleWithoutSuperglobalsTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Waypoint::create()->attach([CustomersController::class, GzipMethodController::class, RedirectsController::class]);
        // Misleading globals: none of this may leak into the request handled below
        $_SERVER['REQUEST_METHOD'] = 'DELETE';
        $_SERVER['REQUEST_URI'] = '/nowhere';
        $_SERVER['HTTP_ACCEPT_ENCODING'] = 'gzip';
        $_GET = ['status' => 'from-globals'];
        header_remove();
    }

    public function testHandleAnswersFromTheRequestAloneAndSendsNothing(): void
    {
        ob_start();
        $res = Waypoint::create()->handle(Request::fromParts('GET', '/customers/7/orders?status=open', ['X-Request-Id' => 'req-1']));
        $echoed = ob_get_clean();

        $this->assertSame('', $echoed);
        $this->assertSame([], headers_list());
        $this->assertSame(200, $res->getStatus());
        $this->assertSame(['customerId' => '7', 'status' => 'open'], json_decode($res->getBody(), true));
        $this->assertSame('req-1', $res->getHeaders()['X-Request-ID']);
        $this->assertSame((string) strlen($res->getBody()), $res->getHeaders()['Content-Length']);
        $this->assertArrayNotHasKey('Content-Encoding', $res->getHeaders());
    }

    public function testGzipFollowsTheRequestsAcceptEncoding(): void
    {
        $res = Waypoint::create()->handle(Request::fromParts('GET', '/gzip-method/enabled', ['Accept-Encoding' => 'gzip']));

        $this->assertSame('gzip', $res->getHeaders()['Content-Encoding']);
        $this->assertStringContainsString('"aaa', (string) gzdecode($res->getBody()));
        $this->assertSame((string) strlen($res->getBody()), $res->getHeaders()['Content-Length']);
    }

    public function testHeadKeepsTheContentLengthOfTheGetButNoBody(): void
    {
        $res = Waypoint::create()->handle(Request::fromParts('HEAD', '/customers/7/orders'));

        $this->assertSame('', $res->getBody());
        $this->assertGreaterThan(0, (int) $res->getHeaders()['Content-Length']);
    }

    public function testCookiesComeBackSeparately(): void
    {
        $res = Waypoint::create()->handle(Request::fromParts('POST', '/redirects/form'));

        $this->assertContains('session=abc; Path=/; HttpOnly; SameSite=Lax', $res->getCookies());
        $this->assertArrayNotHasKey('Set-Cookie', $res->getHeaders());
    }
}
