<?php

namespace Waypoint\Tests\Unit\Http;

use PHPUnit\Framework\TestCase;
use Waypoint\Http\Request;

final class RequestTest extends TestCase
{
    private array $serverBackup;
    private array $getBackup;
    private array $postBackup;

    protected function setUp(): void
    {
        $this->serverBackup = $_SERVER;
        $this->getBackup = $_GET;
        $this->postBackup = $_POST;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;
        $_GET = $this->getBackup;
        $_POST = $this->postBackup;
    }

    public function testCaptureReadsMethodAndPathIgnoringQueryString(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/customers/1/orders?status=open';

        $req = Request::capture();

        $this->assertSame('POST', $req->method);
        $this->assertSame('/customers/1/orders', $req->path);
    }

    public function testCaptureDefaultsMethodToGetAndPathToSlash(): void
    {
        unset($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);

        $req = Request::capture();

        $this->assertSame('GET', $req->method);
        $this->assertSame('/', $req->path);
    }

    public function testCaptureNormalizesHttpPrefixedHeaders(): void
    {
        $_SERVER['HTTP_X_CUSTOM_HEADER'] = 'abc';
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer token';

        $req = Request::capture();

        $this->assertSame('abc', $req->headers['X-Custom-Header']);
        $this->assertSame('Bearer token', $req->headers['Authorization']);
    }

    public function testCaptureReadsGetIntoQuery(): void
    {
        $_GET = ['search' => 'widgets'];

        $req = Request::capture();

        $this->assertSame('widgets', $req->query('search'));
        $this->assertNull($req->query('missing'));
        $this->assertSame('fallback', $req->query('missing', 'fallback'));
    }

    public function testCapturePrefersJsonBodyOverPostWhenBothArePresent(): void
    {
        $_POST = ['name' => 'from-post'];

        $req = Request::capture(rawBody: json_encode(['name' => 'from-json']));

        $this->assertSame(['name' => 'from-json'], $req->body());
    }

    public function testCaptureFallsBackToPostWhenNoJsonBodyPresent(): void
    {
        $_POST = ['name' => 'Widget'];

        $req = Request::capture();

        $this->assertSame(['name' => 'Widget'], $req->body());
        $this->assertSame('Widget', $req->body('name'));
        $this->assertSame('n/a', $req->body('missing', 'n/a'));
    }

    public function testInputIsAnAliasForBody(): void
    {
        $_POST = ['email' => 'a@b.com'];

        $req = Request::capture();

        $this->assertSame($req->body('email'), $req->input('email'));
    }

    public function testAcceptPartialDefaultsToFalse(): void
    {
        $req = Request::capture();
        $this->assertFalse($req->acceptPartial);
    }
}
