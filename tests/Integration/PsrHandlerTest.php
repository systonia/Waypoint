<?php

namespace Waypoint\Tests\Integration;

use Nyholm\Psr7\Factory\Psr17Factory;
use Waypoint\Psr\Handler;
use Waypoint\Tests\Fixtures\Controllers\ArgBindingController;
use Waypoint\Waypoint;

/** Waypoint\Psr\Handler: a PSR-7 request in, through App::handle(), a PSR-7 response out. */
final class PsrHandlerTest extends IntegrationTestCase
{
    public function testARequestGoesThroughTheAppAndComesBackAsPsr7(): void
    {
        $app = Waypoint::create();
        $app->attach([ArgBindingController::class]);
        $factory = new Psr17Factory();

        $response = (new Handler($app, $factory))->handle(
            $factory->createServerRequest('GET', 'http://example.test/argbinding/response-param')->withHeader('X-Request-Id', 'id-psr'),
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['ok' => true], json_decode((string) $response->getBody(), true));
        $this->assertSame('yes', $response->getHeaderLine('X-From-Param'));
        $this->assertSame('id-psr', $response->getHeaderLine('X-Request-ID'));
    }

    public function testTheRequestKeepsWhatAServerOrAMiddlewareParsed(): void
    {
        $factory = new Psr17Factory();
        $request = Handler::toWaypoint(
            $factory->createServerRequest('POST', 'https://example.test/orders?raw=1')
                ->withHeader('Content-Type', 'multipart/form-data; boundary=x')
                ->withAddedHeader('Accept', 'text/html')->withAddedHeader('Accept', 'application/json')
                ->withQueryParams(['page' => '2'])
                ->withCookieParams(['session' => 'abc', 'nested' => ['no']])
                ->withParsedBody(['name' => 'Ada']),
        );

        $this->assertSame('POST', $request->method);
        $this->assertSame('/orders', $request->path);
        $this->assertTrue($request->https);
        $this->assertSame(['page' => '2'], $request->query);
        $this->assertSame(['session' => 'abc'], $request->cookies);
        $this->assertSame(['name' => 'Ada'], $request->body);
        $this->assertSame('text/html, application/json', $request->headers['Accept']);
    }

    public function testUploadsArriveAsWaypointFilesWithoutEmptyInputs(): void
    {
        $factory = new Psr17Factory();
        $request = Handler::toWaypoint(
            $factory->createServerRequest('POST', 'http://example.test/upload')->withUploadedFiles([
                'doc' => $factory->createUploadedFile($factory->createStream('pdf bytes'), 9, UPLOAD_ERR_OK, 'a.pdf', 'application/pdf'),
                'empty' => $factory->createUploadedFile($factory->createStream(''), 0, UPLOAD_ERR_NO_FILE),
                'photos' => [$factory->createUploadedFile($factory->createStream('x'), 1, UPLOAD_ERR_OK, 'x.jpg', 'image/jpeg')],
            ]),
        );

        $this->assertSame(['doc', 'photos'], array_keys($request->files));
        $this->assertSame(['a.pdf', 'application/pdf', 9, 'pdf bytes'], [$request->file('doc')?->name, $request->file('doc')->type, $request->file('doc')->size, $request->file('doc')->contents()]);
        $this->assertSame('x.jpg', $request->file('photos.0')?->name);
    }
}
